<?php

namespace App\Support;

use App\Models\Client;
use App\Models\HostingRecord;
use App\Models\Invoice;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Renewal;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Everything with a date, gathered for the Calendar. Each kind is only included when the
 * signed-in person is allowed to see those records elsewhere in the app.
 */
class CalendarEvents
{
    /** type => [legend label, badge classes, legend dot class, label for one item] */
    public const TYPES = [
        'birthday' => ['Birthdays', 'bg-pink-100 text-pink-800', 'bg-pink-500', 'Birthday'],
        'client' => ['Clients added', 'bg-emerald-100 text-emerald-800', 'bg-emerald-500', 'Client added'],
        'hosting' => ['Hosting payments', 'bg-sky-100 text-sky-800', 'bg-sky-500', 'Hosting payment'],
        'renewal' => ['Renewals', 'bg-indigo-100 text-indigo-800', 'bg-indigo-500', 'Renewal'],
        'invoice' => ['Invoices due', 'bg-amber-100 text-amber-800', 'bg-amber-500', 'Invoice'],
        'quote' => ['Quotes expiring', 'bg-violet-100 text-violet-800', 'bg-violet-500', 'Quote expiry'],
        'task' => ['Tasks & follow-ups', 'bg-stone-200 text-stone-800', 'bg-stone-500', 'Task'],
        'project' => ['Project deadlines', 'bg-teal-100 text-teal-800', 'bg-teal-500', 'Project'],
        'lead' => ['Lead next actions', 'bg-orange-100 text-orange-800', 'bg-orange-500', 'Lead action'],
        'team' => ['Leave & holidays', 'bg-violet-100 text-violet-800', 'bg-violet-500', 'Team'],
        'careers' => ['Interviews & job closing dates', 'bg-rose-100 text-rose-800', 'bg-rose-500', 'Careers'],
    ];

    /** The kinds of event this person may see. @return string[] */
    public static function allowedTypes(User $user): array
    {
        $team = Gate::forUser($user)->allows('workspace');
        $clients = Gate::forUser($user)->allows('clients.view');

        return array_keys(array_filter([
            'birthday' => true,
            'client' => $clients,
            'hosting' => $clients,
            'renewal' => $team && $clients,
            'invoice' => Gate::forUser($user)->allows('finance'),
            'quote' => Gate::forUser($user)->allows('sales.manage'),
            'task' => Gate::forUser($user)->allows('tasks.view'),
            'project' => $team && $clients,
            'lead' => Gate::forUser($user)->allows('sales.manage'),
            'team' => $team,
            'careers' => Gate::forUser($user)->allows('careers.manage'),
        ]));
    }

    /**
     * @param  string[]  $types  kinds to include (already limited to allowedTypes)
     * @return Collection<int, array{date:string, type:string, title:string, detail:?string, url:?string, alert:bool}>
     */
    public static function between(User $user, Carbon $from, Carbon $to, array $types): Collection
    {
        $types = array_values(array_intersect($types, self::allowedTypes($user)));
        [$start, $end] = [$from->toDateString(), $to->toDateString()];
        $today = today_local()->toDateString();
        $has = fn (string $type) => in_array($type, $types, true);
        $canOpenClients = Gate::forUser($user)->allows('clients.view');
        $clientUrl = fn (?int $id, string $tab = 'overview') => $id && $canOpenClients ? route('clients.show', array_filter(['client' => $id, 'tab' => $tab === 'overview' ? null : $tab])) : null;
        // Sub Admins only ever see the clients they added, here as everywhere else.
        $ownClients = fn ($query) => $query->whereHas('client', fn ($q) => $q->visibleTo($user)->whereNull('archived_at'));
        $events = collect();
        $add = function (string $type, $date, string $title, ?string $detail = null, ?string $url = null, bool $alert = false) use (&$events) {
            $events->push(['date' => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date, 'type' => $type, 'title' => $title, 'detail' => $detail, 'url' => $url, 'alert' => $alert]);
        };

        if ($has('birthday')) {
            // Birthdays repeat every year, so match on month and day inside the visible range.
            foreach (User::query()->where('is_active', true)->whereNotNull('date_of_birth')->get() as $person) {
                for ($year = $from->year; $year <= $to->year; $year++) {
                    $birthday = self::birthdayIn($person->date_of_birth, $year);
                    if ($birthday >= $start && $birthday <= $end) {
                        $add('birthday', $birthday, $person->name.'’s birthday', $person->role_label);
                    }
                }
            }
        }

        if ($has('client')) {
            // created_at is stored in UTC; show it on the business-time-zone day.
            Client::query()->visibleTo($user)
                ->whereBetween('created_at', [$from->copy()->startOfDay()->utc(), $to->copy()->endOfDay()->utc()])->get()
                ->each(fn (Client $c) => $add('client', $c->created_at->timezone(crm_tz()), $c->name, 'Client added', $clientUrl($c->id)));
        }

        if ($has('hosting')) {
            $ownClients(HostingRecord::query()->with('client')->where('status', 'active')->whereBetween('next_payment_date', [$start, $end]))->get()
                ->each(fn (HostingRecord $h) => $add('hosting', $h->next_payment_date, $h->domain, $h->client->name.' · hosting payment '.money($h->renewal_amount), $clientUrl($h->client_id, 'renewals'), $h->next_payment_date->toDateString() < $today));
        }

        if ($has('renewal')) {
            $ownClients(Renewal::query()->with('client')->where('status', 'active')->whereBetween('renewal_date', [$start, $end]))->get()
                ->each(fn (Renewal $r) => $add('renewal', $r->renewal_date, $r->name, $r->client->name.' · '.(Renewal::TYPES[$r->type] ?? $r->type).' renewal '.money($r->amount), $clientUrl($r->client_id, 'renewals'), $r->renewal_date->toDateString() < $today));
        }

        if ($has('invoice')) {
            Invoice::query()->with('client')->whereIn('status', Invoice::OPEN_STATUSES)->whereBetween('due_date', [$start, $end])->get()
                ->each(fn (Invoice $i) => $add('invoice', $i->due_date, $i->number.' due', $i->client->name.' · balance '.money($i->balance, $i->currency), route('invoices.show', $i), $i->isOverdue()));
            Invoice::query()->with('client')->where('status', 'draft')->whereBetween('planned_send_date', [$start, $end])->get()
                ->each(fn (Invoice $i) => $add('invoice', $i->planned_send_date, $i->number.' to send', $i->client->name.' · draft '.money($i->total, $i->currency), route('invoices.show', $i), $i->planned_send_date->toDateString() <= $today));
        }

        if ($has('quote')) {
            Quote::query()->with(['client', 'lead'])->where('status', 'sent')->whereBetween('expiry_date', [$start, $end])->get()
                ->each(fn (Quote $q) => $add('quote', $q->expiry_date, $q->number.' expires', $q->partyName().' · '.$q->title, route('quotes.show', $q), $q->isExpired()));
        }

        if ($has('task')) {
            Task::query()->with(['client', 'lead', 'assignees'])->open()->whereBetween('due_date', [$start, $end])->get()
                ->each(fn (Task $t) => $add('task', $t->due_date, $t->title, $t->subjectName().' · '.$t->assigneeNames(), $clientUrl($t->client_id, 'tasks') ?? route('tasks.index'), $t->isOverdue()));
        }

        if ($has('project')) {
            Project::query()->with('client')->open()->whereBetween('deadline', [$start, $end])->get()
                ->each(fn (Project $p) => $add('project', $p->deadline, $p->name.' deadline', $p->client->name, $clientUrl($p->client_id, 'projects'), $p->isOverdue()));
            Project::query()->with('client')->open()->whereBetween('next_milestone_date', [$start, $end])->get()
                ->each(fn (Project $p) => $add('project', $p->next_milestone_date, $p->next_milestone ?: 'Milestone', $p->client->name.' · '.$p->name, $clientUrl($p->client_id, 'projects'), $p->next_milestone_date->toDateString() < $today));
        }

        if ($has('lead')) {
            Lead::query()->open()->whereBetween('next_action_date', [$start, $end])->get()
                ->each(fn (Lead $l) => $add('lead', $l->next_action_date, $l->next_action ?: 'Follow up', $l->name, route('leads.show', $l), $l->next_action_date->toDateString() < $today));
        }

        if ($has('team')) {
            // Company holidays, and who is away on approved leave (one entry on each day of the leave).
            foreach (WorkCalendar::holidays($start, $end) as $date => $name) {
                $add('team', $date, $name, 'Company holiday', route('attendance'));
            }
            // Leave, one entry on each working day of it. Managers see everyone's, approved or still waiting;
            // everyone else sees their own.
            $everyone = Gate::forUser($user)->allows('team.manage');
            LeaveRequest::query()->with(['user', 'type'])->whereIn('status', ['approved', 'pending'])->overlapping($start, $end)
                ->when(! $everyone, fn ($q) => $q->where('user_id', $user->id))->get()
                ->each(function (LeaveRequest $l) use ($add, $start, $end, $user, $everyone) {
                    $who = $l->user_id === $user->id ? 'My leave' : ($l->user?->name ?? 'Someone').' on leave';
                    $title = $who.' · '.($l->type?->code ?: ($l->type?->name ?? 'Leave')).($l->status === 'pending' ? ' (waiting)' : '');
                    $detail = ($l->type?->name ?? 'Leave').' · '.($l->day_part === 'full' ? 'Full day' : LeaveRequest::DAY_PARTS[$l->day_part]).($l->status === 'pending' ? ' · waiting for approval' : '');
                    $url = route('leave', $l->status === 'pending' && $everyone && $l->user_id !== $user->id ? ['tab' => 'review'] : []);
                    for ($day = $l->from_date->copy(); $day->lte($l->to_date); $day->addDay()) {
                        if ($day->toDateString() >= $start && $day->toDateString() <= $end && WorkCalendar::workingDays($day->toDateString(), $day->toDateString()) > 0) {
                            $add('team', $day->toDateString(), $title, $detail, $url);
                        }
                    }
                });
        }

        if ($has('careers')) {
            // Interview times are stored in UTC; the calendar day is the one in the business time zone.
            JobApplication::query()->whereNotIn('status', ['rejected'])
                ->whereBetween('interview_at', [$from->copy()->startOfDay()->utc(), $to->copy()->endOfDay()->utc()])->get()
                ->each(function (JobApplication $a) use ($add) {
                    $when = $a->interview_at->timezone(crm_tz());
                    $add('careers', $when->toDateString(), 'Interview: '.$a->name, $a->vacancy_title.' · '.$when->format('g:i A'), route('careers.applications', ['open' => $a->id]));
                });
            JobVacancy::query()->where('status', 'open')->whereBetween('closes_on', [$start, $end])->get()
                ->each(fn (JobVacancy $v) => $add('careers', $v->closes_on, 'Vacancy closes: '.$v->title, 'Applications stop after this date', route('careers.vacancies'), $v->closes_on->toDateString() < $today));
        }

        $order = array_flip(array_keys(self::TYPES));

        return $events->sortBy(fn ($e) => $e['date'].sprintf('%02d', $order[$e['type']]).$e['title'])->values();
    }

    /** The date a birthday falls on in a given year. 29 February is shown on 28 February outside leap years. */
    public static function birthdayIn(\DateTimeInterface $dateOfBirth, int $year): string
    {
        [$month, $day] = [(int) $dateOfBirth->format('n'), (int) $dateOfBirth->format('j')];
        if ($month === 2 && $day === 29 && ! Carbon::create($year)->isLeapYear()) {
            $day = 28;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
