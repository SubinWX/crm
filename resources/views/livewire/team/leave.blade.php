<div>
    <x-page-header title="Leave" :subtitle="$manager ? 'Apply for leave, and approve or reject requests from the team.' : 'Apply for leave and follow your requests.'">
        <select class="input w-auto" wire:model.live="year" aria-label="Year">
            @foreach ($years as $y) <option value="{{ $y }}">{{ $y }}</option> @endforeach
        </select>
        <button type="button" class="btn-primary" wire:click="apply"><x-icon name="plus" /> Apply for leave</button>
    </x-page-header>

    {{-- What you have of each leave type this year --}}
    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach ($balances as $b)
            <div class="card p-4" wire:key="balance-{{ $b['type']->id }}" title="{{ $b['type']->name }}">
                <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $b['code'] }} <span class="font-normal normal-case">· {{ $b['type']->name }}</span></p>
                @if ($b['left'] === null)
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-stone-900">{{ fmt_days($b['used']) }}</p>
                    <p class="text-xs text-stone-500">taken in {{ $year }} · not paid</p>
                @else
                    <p @class(['mt-1 text-2xl font-semibold tabular-nums', 'text-red-700' => $b['left'] <= 0, 'text-stone-900' => $b['left'] > 0])>{{ fmt_days($b['left']) }}</p>
                    <p class="text-xs text-stone-500">left of {{ fmt_days($b['allowance']) }} · {{ fmt_days($b['used']) }} taken</p>
                @endif
            </div>
        @endforeach
        <div class="card p-4">
            <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Waiting</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums text-stone-900">{{ fmt_days($pendingDays) }}</p>
            <p class="text-xs text-stone-500">days waiting for approval</p>
        </div>
    </div>

    @if ($manager)
        @if ($awayToday->isNotEmpty())
            <div class="mb-5 rounded-md border border-brand-200 bg-brand-50 p-3 text-sm text-brand-900">
                <strong>On leave today:</strong>
                {{ $awayToday->map(fn ($l) => ($l->user?->name ?? 'Someone').($l->day_part !== 'full' ? ' ('.(\App\Models\LeaveRequest::SHORT_PARTS[$l->day_part] ?? '').')' : ''))->implode(', ') }}
            </div>
        @endif
        <div class="mb-5 flex gap-6 overflow-x-auto border-b border-stone-200" role="tablist">
            <button type="button" role="tab" @class(['tab', 'tab-active' => $tab === 'mine']) wire:click="$set('tab', 'mine')">My leave</button>
            <button type="button" role="tab" @class(['tab', 'tab-active' => $tab === 'review']) wire:click="$set('tab', 'review')">To approve @if ($pendingCount) <span class="ml-1 rounded-full bg-red-600 px-1.5 text-[11px] font-bold text-white">{{ $pendingCount }}</span> @endif</button>
            <button type="button" role="tab" @class(['tab', 'tab-active' => $tab === 'all']) wire:click="$set('tab', 'all')">Everyone’s leave</button>
            <button type="button" role="tab" @class(['tab', 'tab-active' => $tab === 'balances']) wire:click="$set('tab', 'balances')">Balances</button>
            <button type="button" role="tab" @class(['tab', 'tab-active' => $tab === 'types']) wire:click="$set('tab', 'types')">Leave types</button>
        </div>
    @endif

    @if ($tab === 'balances')
        <div class="card">
            <div class="card-header"><h2 class="card-title">Leave left for each person in {{ $year }}</h2><span class="text-xs text-stone-500">Left / allowed so far. “Edit” sets a joining date, a custom allowance or extra days.</span></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Person</th><th>Joined</th>@foreach ($balances as $b)<th class="num" title="{{ $b['type']->name }}">{{ $b['code'] }}</th>@endforeach<th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($team as $member)
                            <tr wire:key="bal-{{ $member->id }}">
                                <td><span class="flex items-center gap-2"><x-avatar :src="$member->avatarUrl()" :name="$member->name" size="size-7" class="text-[10px]" />
                                    @if ($member->id === auth()->id()) <a href="{{ route('profile') }}" class="hover:underline">{{ $member->name }}</a>
                                    @elseif (auth()->user()->managesProfileOf($member)) <a href="{{ route('profile.show', $member) }}" class="hover:underline">{{ $member->name }}</a>
                                    @else {{ $member->name }} @endif</span></td>
                                <td class="whitespace-nowrap">{{ fmt_date($member->joined_on, 'Not set') }}</td>
                                @foreach ($teamBalances[$member->id] ?? [] as $b)
                                    <td class="num whitespace-nowrap">
                                        @if ($b['left'] === null) <span class="text-stone-900">{{ fmt_days($b['used']) }}</span> <span class="text-xs text-stone-500">taken</span>
                                        @else <span @class(['font-medium', 'text-red-700' => $b['left'] <= 0, 'text-stone-900' => $b['left'] > 0])>{{ fmt_days($b['left']) }}</span> <span class="text-xs text-stone-500">/ {{ fmt_days($b['allowance']) }}</span>@if ($b['custom'] || $b['extra'] != 0)<span class="ml-1 text-xs text-brand-700" title="Custom balance">●</span>@endif
                                        @endif
                                    </td>
                                @endforeach
                                <td class="text-right"><button type="button" class="btn-ghost btn-sm" wire:click="editBalance({{ $member->id }})">Edit</button></td>
                            </tr>
                        @empty
                            <x-empty :colspan="$balances->count() + 3">Nobody on the team yet.</x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($tab === 'types')
        <div class="card">
            <div class="card-header"><h2 class="card-title">Leave types and yearly allowances</h2><button type="button" class="btn-primary btn-sm" wire:click="editType"><x-icon name="plus" /> Add leave type</button></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Code</th><th>Leave type</th><th>Paid</th><th class="num">Days per year</th><th>Given</th><th>After long service</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @foreach ($allTypes as $t)
                            <tr wire:key="type-{{ $t->id }}">
                                <td class="font-semibold">{{ $t->code ?: '—' }}</td>
                                <td>{{ $t->name }}</td>
                                <td>{{ $t->is_paid ? 'Yes' : 'No (loss of pay)' }}</td>
                                <td class="num">{{ ! $t->is_paid || $t->yearly_days === null ? 'No limit' : ($t->accrual === 'manual' ? '—' : fmt_days($t->yearly_days)) }}</td>
                                <td>{{ $t->is_paid ? (\App\Support\LeaveBalance::ACCRUALS[$t->accrual] ?? $t->accrual) : '—' }}</td>
                                <td>{{ $t->senior_days !== null ? fmt_days($t->senior_days).' days after '.$t->senior_after_years.' '.str('year')->plural($t->senior_after_years) : '—' }}</td>
                                <td><span class="{{ $t->is_active ? 'badge-green' : 'badge-gray' }}">{{ $t->is_active ? 'In use' : 'Hidden' }}</span></td>
                                <td class="text-right"><button type="button" class="btn-ghost btn-sm" wire:click="editType({{ $t->id }})">Edit</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-stone-200 p-4 text-sm text-stone-600">These rules apply to everyone automatically. For one person, set a custom figure or add days (for example earned leave) in the <strong>Balances</strong> tab.</p>
        </div>
    @else
    <div class="card">
        @if ($tab === 'all')
            <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
                <select class="input w-auto" wire:model.live="person" aria-label="Person">
                    <option value="">Everyone</option>
                    @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
                </select>
                <p class="ml-auto text-sm text-stone-600">Approved in {{ $year }}: <strong class="text-stone-900">{{ fmt_days((float) $list->where('status', 'approved')->sum('days')) }} days</strong></p>
            </div>
        @endif
        <div class="table-wrap">
            <table class="table">
                <thead><tr>@if ($tab !== 'mine')<th>Person</th>@endif<th>Dates</th><th>Type</th><th class="num">Days</th><th>Reason</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($list as $leave)
                        <tr wire:key="leave-{{ $leave->id }}">
                            @if ($tab !== 'mine') <td><span class="flex items-center gap-2"><x-avatar :src="$leave->user?->avatarUrl()" :name="$leave->user?->name ?? '?'" size="size-7" class="text-[10px]" /> {{ $leave->user?->name ?? '—' }}</span></td> @endif
                            <td class="whitespace-nowrap">{{ $leave->period() }}<p class="text-xs text-stone-500">Applied {{ fmt_date($leave->created_at->timezone(crm_tz())) }}</p></td>
                            <td>{{ $leave->type?->name ?? '—' }}</td>
                            <td class="num">{{ fmt_days((float) $leave->days) }}</td>
                            <td class="max-w-72"><p class="line-clamp-2">{{ $leave->reason ?: '—' }}</p></td>
                            <td>
                                <span class="{{ \App\Models\LeaveRequest::BADGES[$leave->status] ?? 'badge-gray' }}">{{ \App\Models\LeaveRequest::STATUSES[$leave->status] ?? $leave->status }}</span>
                                @if ($leave->reviewed_by && $tab !== 'review') <p class="mt-0.5 max-w-56 text-xs text-stone-500">by {{ $leave->reviewer?->name ?? 'a manager' }}@if ($leave->review_note): {{ $leave->review_note }}@endif</p> @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @if ($manager && $leave->status === 'pending' && $tab !== 'mine')
                                    <button type="button" class="btn-primary btn-sm" wire:click="decide({{ $leave->id }}, 'approved')">Approve</button>
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="decide({{ $leave->id }}, 'rejected')">Reject</button>
                                @elseif ($leave->user_id === auth()->id() && ($leave->status === 'pending' || ($leave->status === 'approved' && $leave->from_date->toDateString() > $today)))
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="cancel({{ $leave->id }})" wire:confirm="Cancel this leave?">Cancel</button>
                                @else <span class="text-xs text-stone-400">—</span> @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="$tab === 'mine' ? 6 : 7">{{ ['mine' => 'You have no leave in '.$year.'.', 'review' => 'Nothing is waiting for approval.', 'all' => 'No leave in '.$year.'.'][$tab] }}</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @endif

    @if ($showForm)
        <x-modal title="Apply for leave" size="max-w-xl">
            <form wire:submit="submit" class="grid gap-4 sm:grid-cols-2">
                @if ($manager)
                    <x-field label="Leave for" name="form.user_id" class="sm:col-span-2" hint="Leave you add for someone else is approved straight away.">
                        <select class="input" wire:model.live="form.user_id">
                            <option value="">Myself</option>
                            @foreach ($team->where('id', '!=', auth()->id()) as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
                        </select>
                    </x-field>
                @endif
                <x-field label="Leave type" name="form.leave_type_id" required class="sm:col-span-2">
                    <select class="input" wire:model="form.leave_type_id">
                        <option value="">Select leave type…</option>
                        @foreach ($types as $b)
                            <option value="{{ $b['type']->id }}">{{ $b['code'] }} · {{ $b['type']->name }} ({{ $b['left'] === null ? fmt_days($b['used']).' taken, not paid' : fmt_days($b['left'] - $b['pending']).' remaining' }})</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="First day" name="form.from_date" required><input class="input" type="date" wire:model.live="form.from_date"></x-field>
                <x-field label="Last day" name="form.to_date" required><input class="input" type="date" wire:model.live="form.to_date" min="{{ $form['from_date'] }}"></x-field>
                @if ($form['from_date'] === $form['to_date'])
                    <x-field label="Full day / half day / quarter day" name="form.day_part" class="sm:col-span-2" hint="A quarter day (QLOP) is always loss of pay.">
                        <select class="input" wire:model.live="form.day_part">
                            @foreach (\App\Models\LeaveRequest::DAY_PARTS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                @endif
                <x-field label="Reason" name="form.reason" required class="sm:col-span-2"><textarea class="input" rows="3" wire:model="form.reason" maxlength="1000" placeholder="A short reason for the person approving it."></textarea></x-field>
                <p class="rounded-md bg-stone-50 p-3 text-sm text-stone-700 sm:col-span-2">
                    This uses <strong>{{ fmt_days($requestedDays) }} working {{ $requestedDays == 1 ? 'day' : 'days' }}</strong>. Weekly offs and company holidays in between are not counted.
                </p>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">{{ $manager && $form['user_id'] !== '' ? 'Add leave' : 'Send for approval' }}</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($deciding)
        <x-modal :title="($decision === 'approved' ? 'Approve' : 'Reject').' leave for '.($deciding->user?->name ?? 'this person')" size="max-w-lg">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="label">Dates</dt><dd class="text-stone-900">{{ $deciding->period() }}</dd></div>
                <div><dt class="label">Type and days</dt><dd class="text-stone-900">{{ $deciding->type?->name ?? '—' }} · {{ fmt_days((float) $deciding->days) }}</dd></div>
                <div class="sm:col-span-2"><dt class="label">Reason</dt><dd class="whitespace-pre-line text-stone-900">{{ $deciding->reason ?: '—' }}</dd></div>
            </dl>
            <form wire:submit="confirmDecision" class="mt-4 border-t border-stone-200 pt-4">
                <x-field :label="$decision === 'rejected' ? 'Why is it rejected?' : 'Note (optional)'" name="note" :required="$decision === 'rejected'" hint="They will see this.">
                    <textarea class="input" rows="2" wire:model="note" maxlength="500"></textarea>
                </x-field>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="{{ $decision === 'approved' ? 'btn-primary' : 'btn-primary !bg-red-700 hover:!bg-red-800' }}">{{ $decision === 'approved' ? 'Approve leave' : 'Reject leave' }}</button>
                </div>
            </form>
        </x-modal>
    @endif
    @if ($showTypeForm)
        <x-modal :title="$editingTypeId ? 'Edit leave type' : 'Add leave type'" size="max-w-xl">
            <form wire:submit="saveType" class="grid gap-4 sm:grid-cols-2">
                <x-field label="Name" name="typeForm.name" required><input class="input" type="text" wire:model="typeForm.name" maxlength="60" placeholder="e.g. Casual Leave"></x-field>
                <x-field label="Short code" name="typeForm.code" required hint="Shown on balances, like CL or ML."><input class="input uppercase" type="text" wire:model="typeForm.code" maxlength="6" placeholder="CL"></x-field>
                <x-field label="Paid leave?" name="typeForm.is_paid">
                    <select class="input" wire:model.live="typeForm.is_paid"><option value="1">Yes, with a yearly limit</option><option value="0">No, loss of pay (no limit)</option></select>
                </x-field>
                <x-field label="Status" name="typeForm.is_active">
                    <select class="input" wire:model="typeForm.is_active"><option value="1">In use</option><option value="0">Hidden from the form</option></select>
                </x-field>
                @if ($typeForm['is_paid'] === '1')
                    <x-field label="How it is given" name="typeForm.accrual" class="sm:col-span-2">
                        <select class="input" wire:model.live="typeForm.accrual">
                            @foreach (\App\Support\LeaveBalance::ACCRUALS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                    @if ($typeForm['accrual'] !== 'manual')
                        <x-field label="Days per year" name="typeForm.yearly_days" required class="sm:col-span-2"><input class="input max-w-32" type="number" step="0.25" min="0" max="366" wire:model="typeForm.yearly_days"></x-field>
                        <x-field label="Higher allowance: days" name="typeForm.senior_days" hint="Optional, for long service."><input class="input" type="number" step="0.25" min="0" max="366" wire:model="typeForm.senior_days" placeholder="e.g. 18"></x-field>
                        <x-field label="After completing (years)" name="typeForm.senior_after_years" hint="Counted from each person's joining date."><input class="input" type="number" step="1" min="1" max="50" wire:model="typeForm.senior_after_years" placeholder="e.g. 2"></x-field>
                    @else
                        <p class="rounded-md bg-stone-50 p-3 text-sm text-stone-600 sm:col-span-2">Everyone starts at zero. Add days for each person in the Balances tab.</p>
                    @endif
                @endif
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Save leave type</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($balanceUser)
        <x-modal :title="'Leave balances for '.$balanceUser->name.' · '.$year" size="max-w-2xl">
            <form wire:submit="saveBalance" class="space-y-4">
                <x-field label="Joining date" name="balanceForm.joined_on" hint="Used for allowances that grow with years of service."><input class="input max-w-48" type="date" wire:model="balanceForm.joined_on"></x-field>
                <div class="table-wrap rounded-md border border-stone-200">
                    <table class="table">
                        <thead><tr><th>Leave type</th><th>Custom days for {{ $year }}</th><th>Days added (+/−)</th><th>Note</th></tr></thead>
                        <tbody>
                            @foreach ($limitedTypes as $t)
                                @continue(! isset($balanceForm['rows'][$t->id]))
                                <tr wire:key="brow-{{ $t->id }}">
                                    <td class="whitespace-nowrap"><strong>{{ $t->code }}</strong> <span class="text-xs text-stone-500">{{ $t->name }}</span></td>
                                    <td><input class="input w-28" type="number" step="0.25" min="0" max="366" wire:model="balanceForm.rows.{{ $t->id }}.days" placeholder="Auto: {{ fmt_days(\App\Support\LeaveBalance::automaticYearly($balanceUser, $t, (int) $year)) }}" aria-label="Custom days for {{ $t->name }}">@error('balanceForm.rows.'.$t->id.'.days') <p class="field-error">{{ $message }}</p> @enderror</td>
                                    <td><input class="input w-24" type="number" step="0.25" min="-366" max="366" wire:model="balanceForm.rows.{{ $t->id }}.extra" placeholder="0" aria-label="Days added for {{ $t->name }}">@error('balanceForm.rows.'.$t->id.'.extra') <p class="field-error">{{ $message }}</p> @enderror</td>
                                    <td><input class="input" type="text" maxlength="255" wire:model="balanceForm.rows.{{ $t->id }}.note" placeholder="e.g. Earned in 2026" aria-label="Note for {{ $t->name }}"></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-sm text-stone-600">Leave “Custom days” empty to follow the automatic rule. “Days added” goes on top of it: use it for earned leave, carry forward or corrections.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Save balances</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
