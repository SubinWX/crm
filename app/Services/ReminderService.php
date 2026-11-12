<?php

namespace App\Services;

use App\Jobs\DeliverReminder;
use App\Models\HostingRecord;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\ReminderLog;
use App\Models\Renewal;
use App\Models\User;
use App\Support\WhatsApp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Works out which reminders are due and queues exactly one delivery per
 * (event, record, scheduled date, recipient, channel).
 *
 * Nothing is pre-scheduled: reminder dates are derived from the record's current
 * billing / planned-send date on every run, so editing a date automatically
 * "recalculates" what will be sent.
 */
class ReminderService
{
    public const CHANNELS = ['database', 'mail'];

    public const HOSTING_TWO_MONTHS = 'hosting_two_months';

    public const HOSTING_SEVEN_DAYS = 'hosting_seven_days';

    public const INVOICE_PLANNED_SEND = 'invoice_planned_send';

    /** Re-queue deliveries that were claimed but never confirmed after this many minutes. */
    public const STALE_MINUTES = 60;

    /** @return array<string, int> */
    public function run(?Carbon $today = null): array
    {
        $today = ($today ?? today_local())->copy()->startOfDay();
        $recipients = User::activeSuperAdmins();
        $stats = ['due' => 0, 'queued' => 0, 'requeued' => 0, 'whatsapp' => 0];

        foreach ($this->dueReminders($today) as [$event, $type, $id, $scheduled]) {
            $stats['due']++;
            foreach ($recipients as $recipient) {
                foreach (self::CHANNELS as $channel) {
                    $stats['queued'] += $this->claim($event, $type, $id, $scheduled, $recipient->id, $channel) ? 1 : 0;
                }
            }
        }

        $stats['requeued'] = $this->requeueStale();
        $stats['whatsapp'] = $this->queueClientWhatsApp($today);

        return $stats;
    }

    /**
     * WhatsApp reminders to clients (when switched on in Settings → Email & WhatsApp):
     * hosting 7 days before renewal, other renewals at each configured stage, and unpaid
     * invoices on the configured days around the due date. Each goes once per reminder date.
     */
    public function queueClientWhatsApp(Carbon $today): int
    {
        $queued = 0;
        $count = function (?MessageLog $log) use (&$queued) {
            $queued += $log ? 1 : 0;
        };

        if (WhatsApp::sends('hosting')) {
            HostingRecord::query()->remindable()->with('client')
                ->whereBetween('next_payment_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])->get()
                ->each(fn (HostingRecord $r) => $count(WhatsApp::queue('hosting_due', 'hosting', $r->client, $r->client->phone1, [
                    'client' => $r->client->name, 'item' => $r->domain ?: 'your website', 'date' => fmt_date($r->next_payment_date),
                    'amount' => $r->renewal_amount !== null ? money($r->renewal_amount) : '—',
                ], 'hosting', $r->id, self::hostingSchedule($r)[self::HOSTING_SEVEN_DAYS]->toDateString())));
        }

        if (WhatsApp::sends('renewal')) {
            Renewal::query()->remindable()->with('client')
                ->whereBetween('renewal_date', [$today->toDateString(), $today->copy()->addDays(366)->toDateString()])->get()
                ->each(function (Renewal $r) use ($today, $count) {
                    if ($stage = self::renewalStage($r, $today)) {
                        $count(WhatsApp::queue('renewal_due', 'renewal', $r->client, $r->client->phone1, [
                            'client' => $r->client->name, 'item' => $r->name, 'date' => fmt_date($r->renewal_date),
                            'amount' => $r->amount !== null ? money($r->amount) : '—',
                        ], 'renewal', $r->id, $stage['date']->toDateString()));
                    }
                });
        }

        if (WhatsApp::sends('invoice')) {
            $days = WhatsApp::invoiceDays();
            Invoice::query()->open()->with('client')->whereNotNull('due_date')
                ->whereBetween('due_date', [$today->copy()->subDays(max(0, max($days)) + 3)->toDateString(), $today->copy()->addDays(max(0, -min($days)))->toDateString()])->get()
                ->each(function (Invoice $i) use ($today, $days, $count) {
                    // The latest reminder day that has been reached; a missed scheduler day catches up for 3 days, then lets go.
                    $due = local_date($i->due_date);
                    $reached = collect($days)->filter(fn ($d) => $today->gte($due->copy()->addDays($d)))->max();
                    if ($reached === null || $i->balance <= 0 || $today->gt($due->copy()->addDays($reached + 3)) || $i->client->archived_at) {
                        return;
                    }
                    $count(WhatsApp::queue('invoice_due', 'invoice', $i->client, $i->client->phone1, [
                        'client' => $i->client->name, 'item' => $i->number, 'date' => fmt_date($i->due_date), 'amount' => money($i->balance, $i->currency),
                    ], 'invoice', $i->id, $due->copy()->addDays($reached)->toDateString()));
                });
        }

        return $queued;
    }

    /**
     * The two reminder dates for a hosting record.
     *
     * @return array<string, Carbon>
     */
    public static function hostingSchedule(HostingRecord $record): array
    {
        $due = local_date($record->next_payment_date);

        return $due ? [
            self::HOSTING_TWO_MONTHS => $due->copy()->subMonthsNoOverflow(2),
            self::HOSTING_SEVEN_DAYS => $due->copy()->subDays(7),
        ] : [];
    }

    public static function renewalEvent(int $days): string
    {
        return "renewal_{$days}_days";
    }

    /**
     * The reminder stage a renewal is in today: the closest configured offset that has been reached.
     * Like hosting, an earlier notice is skipped once a later one applies, and missed days catch up.
     *
     * @return array{days:int, date:Carbon}|null
     */
    public static function renewalStage(Renewal $renewal, Carbon $today): ?array
    {
        $date = local_date($renewal->renewal_date);
        if (! $date || $today->gt($date)) {
            return null;
        }
        $reached = collect(Renewal::reminderDays($renewal->type))->filter(fn ($days) => $today->gte($date->copy()->subDays($days)));

        return $reached->isEmpty() ? null : ['days' => $reached->min(), 'date' => $date->copy()->subDays($reached->min())];
    }

    /** @return array<int, array{0:string,1:string,2:int,3:string}> */
    public function dueReminders(Carbon $today): array
    {
        $due = [];

        // Only look at records whose payment date is still ahead (or today) and within range.
        HostingRecord::query()->remindable()
            ->whereBetween('next_payment_date', [$today->toDateString(), $today->copy()->addMonthsNoOverflow(2)->addDays(3)->toDateString()])
            ->get()
            ->each(function (HostingRecord $record) use (&$due, $today) {
                $schedule = self::hostingSchedule($record);
                $twoMonths = $schedule[self::HOSTING_TWO_MONTHS];
                $sevenDays = $schedule[self::HOSTING_SEVEN_DAYS];

                // Each stage has its own window, so a missed scheduler day still catches up
                // but the "two months" notice is not sent once the "seven days" one applies.
                if ($today->gte($sevenDays)) {
                    $due[] = [self::HOSTING_SEVEN_DAYS, 'hosting', $record->id, $sevenDays->toDateString()];
                } elseif ($today->gte($twoMonths)) {
                    $due[] = [self::HOSTING_TWO_MONTHS, 'hosting', $record->id, $twoMonths->toDateString()];
                }
            });

        // Domain, maintenance and retainer renewals. The schedule (days before) is set per type in Settings.
        Renewal::query()->remindable()
            ->whereBetween('renewal_date', [$today->toDateString(), $today->copy()->addDays(366)->toDateString()])
            ->get()
            ->each(function (Renewal $renewal) use (&$due, $today) {
                if ($stage = self::renewalStage($renewal, $today)) {
                    $due[] = [self::renewalEvent($stage['days']), 'renewal', $renewal->id, $stage['date']->toDateString()];
                }
            });

        Invoice::query()->where('status', 'draft')
            ->whereNotNull('planned_send_date')
            ->where('planned_send_date', '<=', $today->toDateString())
            ->get()
            ->each(function (Invoice $invoice) use (&$due) {
                $due[] = [self::INVOICE_PLANNED_SEND, 'invoice', $invoice->id, $invoice->planned_send_date->toDateString()];
            });

        return $due;
    }

    /** Insert the tracking row; only the caller that wins the unique index queues the delivery. */
    protected function claim(string $event, string $type, int $id, string $scheduled, int $recipientId, string $channel): bool
    {
        $inserted = DB::table('reminder_logs')->insertOrIgnore([
            'event' => $event,
            'subject_type' => $type,
            'subject_id' => $id,
            'scheduled_date' => $scheduled,
            'recipient_id' => $recipientId,
            'channel' => $channel,
            'dispatched_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $inserted) {
            return false;
        }

        $log = ReminderLog::where([
            'event' => $event, 'subject_type' => $type, 'subject_id' => $id,
            'scheduled_date' => $scheduled, 'recipient_id' => $recipientId, 'channel' => $channel,
        ])->first();

        DeliverReminder::dispatch($log->id);

        return true;
    }

    /** A worker crash between claim and delivery must not lose the reminder. */
    protected function requeueStale(): int
    {
        $stale = ReminderLog::query()
            ->whereNull('sent_at')->whereNull('cancelled_at')
            ->where('dispatched_at', '<', now()->subMinutes(self::STALE_MINUTES))
            ->where('created_at', '>', now()->subDays(7))
            ->get();

        foreach ($stale as $log) {
            $log->update(['dispatched_at' => now()]);
            DeliverReminder::dispatch($log->id);
        }

        return $stale->count();
    }
}
