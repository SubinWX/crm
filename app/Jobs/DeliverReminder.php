<?php

namespace App\Jobs;

use App\Models\HostingRecord;
use App\Models\Invoice;
use App\Models\ReminderLog;
use App\Models\Renewal;
use App\Notifications\CrmNotification;
use App\Notifications\HostingPaymentDue;
use App\Notifications\InvoiceSendDue;
use App\Notifications\RenewalDue;
use App\Services\ReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Delivers one reminder on one channel to one recipient. Safe to retry: the row is
 * locked, and a reminder that is already sent, cancelled or no longer relevant is skipped.
 */
class DeliverReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $reminderLogId) {}

    public function handle(): void
    {
        DB::transaction(function () {
            $log = ReminderLog::whereKey($this->reminderLogId)->lockForUpdate()->first();

            if (! $log || $log->sent_at || $log->cancelled_at) {
                return;
            }

            $recipient = $log->recipient;
            $notification = $this->buildNotification($log);

            if (! $notification || ! $recipient?->is_active || ! $recipient->isSuperAdmin()) {
                $log->update(['cancelled_at' => now()]);

                return;
            }

            Notification::sendNow($recipient, $notification->only($log->channel));
            $log->update(['sent_at' => now()]);
        });
    }

    /** Returns null when the reminder should be suppressed. */
    protected function buildNotification(ReminderLog $log): ?CrmNotification
    {
        if ($log->subject_type === 'hosting') {
            $record = HostingRecord::query()->remindable()->with('client')->find($log->subject_id);
            if (! $record) {
                return null; // inactive, no longer Paid, archived client or deleted
            }
            // The billing date moved after this reminder was claimed.
            $expected = ReminderService::hostingSchedule($record)[$log->event] ?? null;
            if (! $expected || $expected->toDateString() !== $log->scheduled_date->toDateString()) {
                return null;
            }

            return new HostingPaymentDue($record, $log->event === ReminderService::HOSTING_SEVEN_DAYS ? 'seven_days' : 'two_months');
        }

        if ($log->subject_type === 'renewal') {
            $renewal = Renewal::query()->remindable()->with('client')->find($log->subject_id);
            $stage = $renewal ? ReminderService::renewalStage($renewal, local_date($log->scheduled_date)) : null;
            // Suppressed when cancelled, archived, deleted, or when the date or schedule changed after queueing.
            if (! $stage || ReminderService::renewalEvent($stage['days']) !== $log->event
                || $stage['date']->toDateString() !== $log->scheduled_date->toDateString()) {
                return null;
            }

            return new RenewalDue($renewal, $stage['days']);
        }

        if ($log->subject_type === 'invoice') {
            $invoice = Invoice::with('client')->find($log->subject_id);
            if (! $invoice || $invoice->status !== 'draft'
                || $invoice->planned_send_date?->toDateString() !== $log->scheduled_date->toDateString()) {
                return null; // already sent, voided or rescheduled
            }

            return new InvoiceSendDue($invoice);
        }

        return null;
    }
}
