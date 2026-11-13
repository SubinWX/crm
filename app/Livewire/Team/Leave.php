<?php

namespace App\Livewire\Team;

use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use App\Models\Lookup;
use App\Models\User;
use App\Notifications\LeaveDecided;
use App\Notifications\LeaveRequested;
use App\Support\LeaveBalance;
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
 * "team.manage" (and Super Admins) approve or reject them, see the whole team's leave, add leave
 * for someone, set each person's balances, and manage the leave types and their yearly allowances.
 */
#[Title('Leave')]
class Leave extends Component
{
    /** mine | review | all | balances | types (the last four are for managers) */
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

    /** Leave type being added or edited. */
    public bool $showTypeForm = false;

    public ?int $editingTypeId = null;

    public array $typeForm = [];

    /** Person whose balances are being edited, with one row per leave type. */
    public ?int $balanceUserId = null;

    public array $balanceForm = ['joined_on' => '', 'rows' => []];

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
        if (! $this->manager() || ! in_array($this->tab, ['mine', 'review', 'all', 'balances', 'types'], true)) {
            $this->tab = 'mine';
        }
        $this->resetForm();
        if (request()->boolean('apply')) {
            // The calendar opens the form on the day that was picked.
            $date = (string) request()->query('date');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $this->form['from_date'] = $this->form['to_date'] = $date;
            }
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
        $this->form = ['user_id' => '', 'leave_type_id' => '', 'from_date' => today_local()->toDateString(), 'to_date' => today_local()->toDateString(), 'day_part' => 'full', 'reason' => ''];
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

    /** The person the form is for: yourself, or (managers only) someone on the team. */
    protected function applicant(): User
    {
        $id = (string) ($this->form['user_id'] ?? '');
        if ($id === '' || (int) $id === auth()->id() || ! $this->manager()) {
            return auth()->user();
        }

        return User::team()->active()->find((int) $id) ?? abort(403);
    }

    public function submit(): void
    {
        $person = $this->applicant();
        $forSomeoneElse = $person->id !== auth()->id();
        if (($this->form['from_date'] ?? '') !== ($this->form['to_date'] ?? '')) {
            $this->form['day_part'] = 'full'; // half days only make sense for a single date
        }

        $this->validate([
            'form.leave_type_id' => ['required', Rule::exists('lookups', 'id')->where('type', 'leave_type')->where('is_active', true)],
            'form.from_date' => ['required', 'date', 'after_or_equal:'.today_local()->subDays($forSomeoneElse ? 366 : self::BACKDATE_DAYS)->toDateString(), 'before_or_equal:'.today_local()->addYear()->toDateString()],
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
        if (LeaveRequest::where('user_id', $person->id)->whereIn('status', ['pending', 'approved'])->overlapping($this->form['from_date'], $this->form['to_date'])->exists()) {
            $this->addError('form.from_date', ($forSomeoneElse ? $person->name.' already has' : 'You already have').' leave applied for on some of these dates.');

            return;
        }

        $type = Lookup::findOrFail((int) $this->form['leave_type_id']);
        if ($this->form['day_part'] === 'quarter' && $type->is_paid) {
            $this->addError('form.leave_type_id', 'A quarter day can only be taken as loss of pay. Choose an unpaid leave type.');

            return;
        }
        // A type with a yearly figure cannot go below zero; requests still waiting are counted too.
        $balance = LeaveBalance::for($person, (int) substr($this->form['from_date'], 0, 4), collect([$type]))->first();
        if ($balance['left'] !== null && $days > $balance['left'] - $balance['pending'] + 0.001) {
            $free = max(0, $balance['left'] - $balance['pending']);
            $this->addError('form.leave_type_id', 'Only '.fmt_days($free).' '.($free === 1.0 ? 'day' : 'days').' of '.$type->name.' left'.($balance['pending'] > 0 ? ' (counting requests still waiting)' : '').'. Shorten the dates or use another leave type for the rest.');

            return;
        }

        $leave = LeaveRequest::create([
            'user_id' => $person->id, 'leave_type_id' => (int) $this->form['leave_type_id'], 'from_date' => $this->form['from_date'], 'to_date' => $this->form['to_date'],
            'day_part' => $this->form['day_part'], 'days' => $days, 'reason' => trim($this->form['reason']),
        ]);
        if ($forSomeoneElse) {
            // Leave a manager adds for someone is approved straight away, and the person is told.
            $leave->update(['status' => 'approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'review_note' => 'Added by '.auth()->user()->name]);
            $person->notify(new LeaveDecided($leave->fresh(['reviewer', 'type'])));
            activity('created', 'Leave added for '.$person->name.': '.$leave->period(), $leave);
        } else {
            Notification::send(self::approvers()->where('id', '!=', auth()->id()), new LeaveRequested($leave));
            activity('created', auth()->user()->name.' applied for leave: '.$leave->period(), $leave);
        }

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: $forSomeoneElse ? 'Leave added for '.$person->name.'.' : 'Leave request sent for approval.');
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

    // ── Leave types (managers) ───────────────────────────────────────────────

    public function editType(?int $id = null): void
    {
        abort_unless($this->manager(), 403);
        $type = $id ? Lookup::where('type', 'leave_type')->findOrFail($id) : null;
        $this->editingTypeId = $type?->id;
        $this->typeForm = [
            'name' => (string) $type?->name, 'code' => (string) $type?->code, 'is_paid' => ($type?->is_paid ?? true) ? '1' : '0',
            'yearly_days' => $type && $type->yearly_days !== null ? fmt_days($type->yearly_days) : ($type ? '' : '0'),
            'accrual' => $type?->accrual ?? 'yearly',
            'senior_days' => $type && $type->senior_days !== null ? fmt_days($type->senior_days) : '', 'senior_after_years' => (string) ($type?->senior_after_years ?? ''),
            'is_active' => ($type?->is_active ?? true) ? '1' : '0',
        ];
        $this->resetValidation();
        $this->showTypeForm = true;
    }

    public function saveType(): void
    {
        abort_unless($this->manager(), 403);
        $paid = ($this->typeForm['is_paid'] ?? '1') === '1';
        $this->validate([
            'typeForm.name' => ['required', 'string', 'max:60', Rule::unique('lookups', 'name')->where('type', 'leave_type')->ignore($this->editingTypeId)],
            'typeForm.code' => ['required', 'string', 'max:6', 'regex:/^[A-Za-z0-9]+$/', Rule::unique('lookups', 'code')->where('type', 'leave_type')->ignore($this->editingTypeId)],
            'typeForm.is_paid' => ['required', Rule::in(['0', '1'])],
            'typeForm.yearly_days' => [Rule::requiredIf($paid), 'nullable', 'numeric', 'min:0', 'max:366'],
            'typeForm.accrual' => ['required', Rule::in(array_keys(LeaveBalance::ACCRUALS))],
            'typeForm.senior_days' => ['nullable', 'numeric', 'min:0', 'max:366', 'required_with:typeForm.senior_after_years'],
            'typeForm.senior_after_years' => ['nullable', 'integer', 'min:1', 'max:50', 'required_with:typeForm.senior_days'],
            'typeForm.is_active' => ['required', Rule::in(['0', '1'])],
        ], ['typeForm.code.regex' => 'Use letters and numbers only, like CL or ML.'], [
            'typeForm.name' => 'name', 'typeForm.code' => 'short code', 'typeForm.yearly_days' => 'days per year',
            'typeForm.senior_days' => 'days after long service', 'typeForm.senior_after_years' => 'years completed',
        ]);

        $manual = $this->typeForm['accrual'] === 'manual';
        $data = [
            'name' => trim($this->typeForm['name']), 'code' => strtoupper($this->typeForm['code']), 'is_paid' => $paid,
            // Unpaid leave has no limit; a type filled by hand starts everyone at zero.
            'yearly_days' => $paid ? ($manual ? 0 : (float) $this->typeForm['yearly_days']) : null,
            'accrual' => $paid ? $this->typeForm['accrual'] : 'yearly',
            'senior_days' => $paid && ! $manual && $this->typeForm['senior_days'] !== '' ? (float) $this->typeForm['senior_days'] : null,
            'senior_after_years' => $paid && ! $manual && $this->typeForm['senior_after_years'] !== '' ? (int) $this->typeForm['senior_after_years'] : null,
            'is_active' => $this->typeForm['is_active'] === '1',
        ];
        $this->editingTypeId
            ? Lookup::where('type', 'leave_type')->findOrFail($this->editingTypeId)->update($data)
            : Lookup::create($data + ['type' => 'leave_type', 'sort_order' => (int) Lookup::where('type', 'leave_type')->max('sort_order') + 1]);
        activity('updated', 'Leave type saved: '.$data['name']);

        $this->showTypeForm = false;
        $this->dispatch('toast', message: 'Leave type saved.');
    }

    // ── One person's balances (managers) ─────────────────────────────────────

    public function editBalance(int $userId): void
    {
        abort_unless($this->manager(), 403);
        $user = User::team()->find($userId) ?? abort(403);
        $allocations = LeaveAllocation::where('user_id', $user->id)->where('year', (int) $this->year)->get()->keyBy('leave_type_id');
        $this->balanceUserId = $user->id;
        $this->balanceForm = ['joined_on' => $user->joined_on?->format('Y-m-d') ?? '', 'rows' => []];
        foreach (LeaveBalance::types()->filter(fn ($t) => LeaveBalance::hasLimit($t)) as $type) {
            $a = $allocations[$type->id] ?? null;
            $this->balanceForm['rows'][$type->id] = [
                'days' => $a && $a->days !== null ? fmt_days($a->days) : '', 'extra' => $a && (float) $a->extra != 0.0 ? fmt_days($a->extra) : '', 'note' => (string) $a?->note,
            ];
        }
        $this->resetValidation();
    }

    public function saveBalance(): void
    {
        abort_unless($this->manager(), 403);
        $user = User::team()->find($this->balanceUserId) ?? abort(403);
        $this->validate([
            'balanceForm.joined_on' => ['nullable', 'date', 'before_or_equal:'.today_local()->addYear()->toDateString()],
            'balanceForm.rows.*.days' => ['nullable', 'numeric', 'min:0', 'max:366'],
            'balanceForm.rows.*.extra' => ['nullable', 'numeric', 'min:-366', 'max:366'],
            'balanceForm.rows.*.note' => ['nullable', 'string', 'max:255'],
        ], [], ['balanceForm.joined_on' => 'joining date', 'balanceForm.rows.*.days' => 'custom days', 'balanceForm.rows.*.extra' => 'days added', 'balanceForm.rows.*.note' => 'note']);

        $user->update(['joined_on' => $this->balanceForm['joined_on'] ?: null]);
        $typeIds = LeaveBalance::types()->filter(fn ($t) => LeaveBalance::hasLimit($t))->pluck('id')->all();
        foreach ($this->balanceForm['rows'] as $typeId => $row) {
            if (! in_array((int) $typeId, $typeIds, true)) {
                continue;
            }
            $key = ['user_id' => $user->id, 'leave_type_id' => (int) $typeId, 'year' => (int) $this->year];
            $days = ($row['days'] ?? '') === '' ? null : (float) $row['days'];
            $extra = ($row['extra'] ?? '') === '' ? 0.0 : (float) $row['extra'];
            $note = trim((string) ($row['note'] ?? '')) ?: null;
            // Nothing custom left for this type: the automatic rule applies again.
            $days === null && $extra == 0.0 && $note === null
                ? LeaveAllocation::where($key)->delete()
                : LeaveAllocation::updateOrCreate($key, ['days' => $days, 'extra' => $extra, 'note' => $note, 'updated_by' => auth()->id()]);
        }
        activity('updated', 'Leave balances for '.$user->name.' updated ('.$this->year.')', $user);

        $this->balanceUserId = null;
        $this->dispatch('toast', message: 'Balances saved.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->decidingId = null;
        $this->showTypeForm = false;
        $this->balanceUserId = null;
    }

    public function render()
    {
        $manager = $this->manager();
        if (! $manager) {
            $this->tab = 'mine';
        }
        [$from, $to] = [$this->year.'-01-01', $this->year.'-12-31'];
        $me = auth()->id();

        $balances = LeaveBalance::for(auth()->user(), (int) $this->year);

        $list = match ($this->tab) {
            'review' => LeaveRequest::with(['user', 'type'])->where('status', 'pending')->orderBy('from_date')->get(),
            'all' => LeaveRequest::with(['user', 'type', 'reviewer'])->overlapping($from, $to)
                ->when(ctype_digit($this->person), fn ($q) => $q->where('user_id', (int) $this->person))->latest('from_date')->latest('id')->get(),
            'balances', 'types' => collect(),
            default => LeaveRequest::with(['type', 'reviewer'])->where('user_id', $me)->overlapping($from, $to)->latest('from_date')->latest('id')->get(),
        };
        $today = today_local()->toDateString();

        return view('livewire.team.leave', [
            'manager' => $manager,
            'list' => $list,
            'balances' => $balances,
            // The form shows what the chosen person has left of each type.
            'types' => $this->showForm ? LeaveBalance::for($this->applicant(), (int) substr((string) $this->form['from_date'], 0, 4) ?: (int) $this->year) : collect(),
            'pendingDays' => (float) LeaveRequest::where('user_id', $me)->where('status', 'pending')->whereYear('from_date', $this->year)->sum('days'),
            'team' => $manager && ($this->tab === 'balances' || $this->showForm) ? User::team()->active()->orderBy('name')->get() : collect(),
            'teamBalances' => $manager && $this->tab === 'balances' ? LeaveBalance::forPeople(User::team()->active()->orderBy('name')->get(), (int) $this->year) : [],
            'allTypes' => $manager && $this->tab === 'types' ? LeaveBalance::types(false) : collect(),
            'limitedTypes' => $manager && ($this->tab === 'balances' || $this->balanceUserId) ? LeaveBalance::types()->filter(fn ($t) => LeaveBalance::hasLimit($t))->values() : collect(),
            'balanceUser' => $this->balanceUserId ? User::find($this->balanceUserId) : null,
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
