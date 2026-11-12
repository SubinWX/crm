<div>
    <x-page-header title="Leave" :subtitle="$manager ? 'Apply for leave, and approve or reject requests from the team.' : 'Apply for leave and follow your requests.'">
        <select class="input w-auto" wire:model.live="year" aria-label="Year">
            @foreach ($years as $y) <option value="{{ $y }}">{{ $y }}</option> @endforeach
        </select>
        <button type="button" class="btn-primary" wire:click="apply"><x-icon name="plus" /> Apply for leave</button>
    </x-page-header>

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Paid leave left" :value="rtrim(rtrim(number_format($summary['left'], 1), '0'), '.')" :hint="'of '.rtrim(rtrim(number_format($summary['allowance'], 1), '0'), '.').' days in '.$year" :tone="$summary['left'] <= 0 ? 'danger' : 'default'" />
        <x-stat label="Paid leave taken" :value="rtrim(rtrim(number_format($summary['paid'], 1), '0'), '.')" :hint="$summary['byType']->map(fn ($d, $t) => $t.' '.rtrim(rtrim(number_format($d, 1), '0'), '.'))->implode(' · ') ?: 'None yet'" />
        <x-stat label="Unpaid leave taken" :value="rtrim(rtrim(number_format($summary['unpaid'], 1), '0'), '.')" hint="Not counted against the allowance" />
        <x-stat label="Waiting for approval" :value="rtrim(rtrim(number_format($summary['pending'], 1), '0'), '.')" hint="Days in your pending requests" />
    </div>

    @if ($manager)
        @if ($awayToday->isNotEmpty())
            <div class="mb-5 rounded-md border border-brand-200 bg-brand-50 p-3 text-sm text-brand-900">
                <strong>On leave today:</strong>
                {{ $awayToday->map(fn ($l) => ($l->user?->name ?? 'Someone').($l->day_part !== 'full' ? ' ('.strtolower(\App\Models\LeaveRequest::DAY_PARTS[$l->day_part]).')' : ''))->implode(', ') }}
            </div>
        @endif
        <div class="mb-5 flex gap-6 border-b border-stone-200" role="tablist">
            <button type="button" role="tab" @class(['tab', 'tab-active' => $tab === 'mine']) wire:click="$set('tab', 'mine')">My leave</button>
            <button type="button" role="tab" @class(['tab', 'tab-active' => $tab === 'review']) wire:click="$set('tab', 'review')">To approve @if ($pendingCount) <span class="ml-1 rounded-full bg-red-600 px-1.5 text-[11px] font-bold text-white">{{ $pendingCount }}</span> @endif</button>
            <button type="button" role="tab" @class(['tab', 'tab-active' => $tab === 'all']) wire:click="$set('tab', 'all')">Everyone’s leave</button>
        </div>
    @endif

    <div class="card">
        @if ($tab === 'all')
            <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
                <select class="input w-auto" wire:model.live="person" aria-label="Person">
                    <option value="">Everyone</option>
                    @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
                </select>
                <p class="ml-auto text-sm text-stone-600">Approved in {{ $year }}: <strong class="text-stone-900">{{ rtrim(rtrim(number_format((float) $list->where('status', 'approved')->sum('days'), 1), '0'), '.') }} days</strong></p>
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
                            <td class="num">{{ rtrim(rtrim(number_format((float) $leave->days, 1), '0'), '.') }}</td>
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

    @if ($showForm)
        <x-modal title="Apply for leave" size="max-w-xl">
            <form wire:submit="submit" class="grid gap-4 sm:grid-cols-2">
                <x-field label="Leave type" name="form.leave_type_id" required class="sm:col-span-2">
                    <select class="input" wire:model="form.leave_type_id">
                        <option value="">Select…</option>
                        @foreach ($types as $t) <option value="{{ $t->id }}">{{ $t->name }}{{ $t->is_paid ? '' : ' (unpaid)' }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="First day" name="form.from_date" required><input class="input" type="date" wire:model.live="form.from_date"></x-field>
                <x-field label="Last day" name="form.to_date" required><input class="input" type="date" wire:model.live="form.to_date" min="{{ $form['from_date'] }}"></x-field>
                @if ($form['from_date'] === $form['to_date'])
                    <x-field label="How much of the day" name="form.day_part" class="sm:col-span-2">
                        <select class="input" wire:model.live="form.day_part">
                            @foreach (\App\Models\LeaveRequest::DAY_PARTS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                @endif
                <x-field label="Reason" name="form.reason" required class="sm:col-span-2"><textarea class="input" rows="3" wire:model="form.reason" maxlength="1000" placeholder="A short reason for the person approving it."></textarea></x-field>
                <p class="rounded-md bg-stone-50 p-3 text-sm text-stone-700 sm:col-span-2">
                    This uses <strong>{{ rtrim(rtrim(number_format($requestedDays, 1), '0'), '.') }} working {{ $requestedDays === 1.0 ? 'day' : 'days' }}</strong>. Weekly offs and company holidays in between are not counted.
                    You have {{ rtrim(rtrim(number_format($summary['left'], 1), '0'), '.') }} paid {{ $summary['left'] === 1.0 ? 'day' : 'days' }} left this year.
                </p>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Send for approval</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($deciding)
        <x-modal :title="($decision === 'approved' ? 'Approve' : 'Reject').' leave for '.($deciding->user?->name ?? 'this person')" size="max-w-lg">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="label">Dates</dt><dd class="text-stone-900">{{ $deciding->period() }}</dd></div>
                <div><dt class="label">Type and days</dt><dd class="text-stone-900">{{ $deciding->type?->name ?? '—' }} · {{ rtrim(rtrim(number_format((float) $deciding->days, 1), '0'), '.') }}</dd></div>
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
</div>
