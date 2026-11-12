<?php

namespace App\Livewire\Team;

use App\Models\LeaveRequest;
use App\Models\Lookup;
use App\Models\User;
use App\Notifications\LeaveDecided;
use App\Notifications\LeaveRequested;
use App\Support\WorkCalendar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Team → Leave. Everyone applies for leave and follows their own requests. People with
 * "team.manage" (and Super Admins) approve or reject them and see the whole team's leave.
 */
#[Title('Leave')]
class Leave extends Component
{
    /** mine | review | all */
    #[Url(except: 'mine')]
    public string $tab = 'mine';

    #[Url(except: '')]
    public string $year = '';

    #[Url(except: '')]
    public string $person = '';

    public bool $showForm = false;

    public array $form = [];

    public ?int $decidingId = null;

    public string $decision = 'approved';

    public string $note = '';

    /** How far back leave can be applied for, e.g. sick days reported afterwards. */
    public const BACKDATE_DAYS = 30;

    public function boot(): void
    {
        Gate::authorize('workspace');
    }

    public function mount(): void
    {
        if (! preg_match('/^20\d{2}$/', $this->year)) {
            $this->year = today_local()->format('Y');
        }
        if (! $this->manager() || ! in_array($this->tab, ['mine', 'review', 'all'], true)) {
            $this->tab = 'mine';
        }
        $this->resetForm();
        if (request()->boolean('apply')) {
            $this->showForm = true;
        }
    }

    protected function manager(): bool
    {
        return Gate::allows('team.manage');
    }

    /** Everyone who should hear about a new request. */
    public static function approvers(): Collection
    {
        return User::query()->active()->whereNotNull('password')
            ->where(fn ($q) => $q->where('role', User::ROLE_SUPER_ADMIN)
                ->orWhere(fn ($q) => $q->where('role', User::ROLE_STAFF)->whereJsonContains('permissions', 'team.manage')))
            ->get();
    }

    protected function resetForm(): void
    {
        $this->form = ['leave_type_id' => '', 'from_date' => today_local()->toDateString(), 'to_date' => today_local()->toDateString(), 'day_part' => 'full', 'reason' => ''];
    }

    public function apply(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    /** Working days the request on screen would use, shown while the form is filled in. */
    public function requestedDays(): float
    {
        ['from_date' => $from, 'to_date' => $to] = $this->form;
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $to) || $to < $from) {
            return 0;
        }

        return WorkCalendar::workingDays($from, $to, $this->form['day_part'] ?? 'full');
    }

    public function submit(): void
    {
        if (($this->form['from_date'] ?? '') !== ($this->form['to_date'] ?? '')) {
            $this->form['day_part'] = 'full'; // half days only make sense for a single date
        }

        $this->validate([
            'form.leave_type_id' => ['required', Rule::exists('lookups', 'id')->where('type', 'leave_type')->where('is_active', true)],
            'form.from_date' => ['required', 'date', 'after_or_equal:'.today_local()->subDays(self::BACKDATE_DAYS)->toDateString(), 'before_or_equal:'.today_local()->addYear()->toDateString()],
            'form.to_date' => ['required', 'date', 'after_or_equal:form.from_date', 'before_or_equal:'.today_local()->addYear()->toDateString()],
            'form.day_part' => ['required', Rule::in(array_keys(LeaveRequest::DAY_PARTS))],
            'form.reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'form.from_date.after_or_equal' => 'Leave can be applied for up to '.self::BACKDATE_DAYS.' days back. Ask a manager for anything older.',
            'form.to_date.after_or_equal' => 'The last day cannot be before the first day.',
        ], ['form.leave_type_id' => 'leave type', 'form.from_date' => 'first day', 'form.to_date' => 'last day', 'form.reason' => 'reason']);

        $days = $this->requestedDays();
        if ($days <= 0) {
            $this->addError('form.from_date', 'Those dates are all weekly offs or holidays, so no leave is needed.');

            return;
        }
        if (LeaveRequest::where('user_id', auth()->id())->whereIn('status', ['pending', 'approved'])->overlapping($this->form['from_date'], $this->form['to_date'])->exists()) {
            $this->addError('form.from_date', 'You already have leave applied for on some of these dates.');

            return;
        }

        $leave = LeaveRequest::create([
            'user_id' => auth()->id(), 'leave_type_id' => (int) $this->form['leave_type_id'], 'from_date' => $this->form['from_date'], 'to_date' => $this->form['to_date'],
            'day_part' => $this->form['day_part'], 'days' => $days, 'reason' => trim($this->form['reason']),
        ]);
        Notification::send(self::approvers()->where('id', '!=', auth()->id()), new LeaveRequested($leave));
        activity('created', auth()->user()->name.' applied for leave: '.$leave->period(), $leave);

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Leave request sent for approval.');
    }

    /** You can withdraw a request that is still waiting, or approved leave that has not started yet. */
    public function cancel(int $id): void
    {
        $leave = LeaveRequest::where('user_id', auth()->id())->find($id) ?? abort(403);
        abort_unless($leave->status === 'pending' || ($leave->status === 'approved' && $leave->from_date->toDateString() > today_local()->toDateString()), 422);
        $leave->update(['status' => 'cancelled']);
        activity('updated', auth()->user()->name.' cancelled leave: '.$leave->period(), $leave);
        $this->dispatch('toast', message: 'Leave cancelled.');
    }

    public function decide(int $id, string $decision): void
    {
        abort_unless($this->manager() && in_array($decision, ['approved', 'rejected'], true), 403);
        $this->decidingId = LeaveRequest::where('status', 'pending')->findOrFail($id)->id;
        $this->decision = $decision;
        $this->note = '';
        $this->resetValidation();
    }

    public function confirmDecision(): void
    {
        abort_unless($this->manager(), 403);
        $leave = LeaveRequest::where('status', 'pending')->findOrFail($this->decidingId);
        // Only a Super Admin may decide their own request; anyone else needs a second person.
        if ($leave->user_id === auth()->id() && ! auth()->user()->isSuperAdmin()) {
            $this->addError('note', 'You cannot approve or reject your own leave. Ask another manager.');

            return;
        }
        $this->validate(['note' => [$this->decision === 'rejected' ? 'required' : 'nullable', 'string', 'max:500']], ['note.required' => 'Say briefly why it is rejected, so they know.'], ['note' => 'note']);

        $leave->update(['status' => $this->decision, 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'review_note' => trim($this->note) ?: null]);
        if ($leave->user_id !== auth()->id()) {
            $leave->user?->notify(new LeaveDecided($leave->fresh(['reviewer', 'type'])));
        }
        activity('updated', 'Leave for '.($leave->user?->name ?? 'someone').' '.$this->decision.': '.$leave->period(), $leave);

        $this->decidingId = null;
        $this->dispatch('toast', message: 'Leave '.$this->decision.'.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->decidingId = null;
    }

    public function render()
    {
        $manager = $this->manager();
        if (! $manager) {
            $this->tab = 'mine';
        }
        [$from, $to] = [$this->year.'-01-01', $this->year.'-12-31'];
        $me = auth()->id();

        // This year's approved days, split into paid (counted against the allowance) and unpaid.
        $mineThisYear = LeaveRequest::with('type')->where('user_id', $me)->overlapping($from, $to)->get();
        $approved = $mineThisYear->where('status', 'approved');
        $paid = (float) $approved->filter(fn ($l) => $l->type?->is_paid ?? true)->sum('days');
        $allowance = (float) setting('leave_days_per_year', 12);

        $list = match ($this->tab) {
            'review' => LeaveRequest::with(['user', 'type'])->where('status', 'pending')->orderBy('from_date')->get(),
            'all' => LeaveRequest::with(['user', 'type', 'reviewer'])->overlapping($from, $to)
                ->when(ctype_digit($this->person), fn ($q) => $q->where('user_id', (int) $this->person))->latest('from_date')->latest('id')->get(),
            default => LeaveRequest::with(['type', 'reviewer'])->where('user_id', $me)->overlapping($from, $to)->latest('from_date')->latest('id')->get(),
        };
        $today = today_local()->toDateString();

        return view('livewire.team.leave', [
            'manager' => $manager,
            'list' => $list,
            'types' => Lookup::options('leave_type', $this->form['leave_type_id'] ?? null),
            'summary' => [
                'allowance' => $allowance, 'paid' => $paid, 'left' => max(0, $allowance - $paid),
                'unpaid' => (float) $approved->filter(fn ($l) => ! ($l->type?->is_paid ?? true))->sum('days'),
                'pending' => (float) $mineThisYear->where('status', 'pending')->sum('days'),
                'byType' => $approved->groupBy(fn ($l) => $l->type?->name ?? 'Other')->map(fn ($g) => (float) $g->sum('days'))->sortDesc(),
            ],
            'pendingCount' => $manager ? LeaveRequest::where('status', 'pending')->count() : 0,
            'awayToday' => $manager ? LeaveRequest::with(['user', 'type'])->where('status', 'approved')->overlapping($today, $today)->get() : collect(),
            'people' => $manager && $this->tab === 'all' ? User::team()->orderBy('name')->get(['id', 'name']) : collect(),
            'deciding' => $this->decidingId ? LeaveRequest::with(['user', 'type'])->find($this->decidingId) : null,
            'requestedDays' => $this->showForm ? $this->requestedDays() : 0,
            'years' => range((int) today_local()->format('Y') + 1, (int) today_local()->format('Y') - 3),
            'today' => $today,
        ]);
    }
}
