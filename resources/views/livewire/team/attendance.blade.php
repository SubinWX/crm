<div>
    <x-page-header title="Attendance" :subtitle="$manager ? 'Check in and out, and keep the team’s monthly attendance sheet.' : 'Check in and out, and see your month.'">
        @if ($manager && $tab === 'sheet') <a href="{{ route('attendance.export', ['month' => $month]) }}" class="btn-secondary"><x-icon name="download" /> Export sheet (CSV)</a> @endif
    </x-page-header>

    {{-- Today --}}
    <div class="card mb-5 flex flex-wrap items-center justify-between gap-4 p-4">
        <div class="text-sm">
            <p class="font-semibold text-stone-900">Today, {{ today_local()->format('l, d M Y') }}</p>
            <p class="text-stone-600">
                @if ($todayRecord?->check_in)
                    Checked in at <strong>{{ \Illuminate\Support\Carbon::parse($todayRecord->check_in)->format('g:i A') }}</strong>@if ($todayRecord->check_out), checked out at <strong>{{ \Illuminate\Support\Carbon::parse($todayRecord->check_out)->format('g:i A') }}</strong>@endif.
                @elseif ($todayRecord) Marked as {{ strtolower(\App\Models\AttendanceRecord::STATUSES[$todayRecord->status] ?? $todayRecord->status) }} by a manager.
                @else You have not checked in yet. @endif
            </p>
        </div>
        <div class="flex gap-2">
            @if (! $todayRecord?->check_in)
                <button type="button" class="btn-primary px-5 py-2.5" wire:click="checkIn">Check in</button>
            @elseif (! $todayRecord->check_out)
                <button type="button" class="btn-primary px-5 py-2.5" wire:click="checkOut" wire:confirm="Check out for today?">Check out</button>
            @else
                <span class="badge-green">Done for today</span>
            @endif
        </div>
    </div>

    <div class="mb-5 flex flex-wrap items-center gap-2">
        <button type="button" class="btn-secondary" wire:click="shift(-1)" aria-label="Previous month">←</button>
        <input type="month" class="input w-auto" wire:model.live="month" aria-label="Month">
        <button type="button" class="btn-secondary" wire:click="shift(1)" aria-label="Next month">→</button>
        @if ($manager)
            <div class="ml-auto flex gap-1" role="tablist">
                @foreach (['mine' => 'My month', 'sheet' => 'Team sheet', 'setup' => 'Holidays & settings'] as $key => $label)
                    <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" @class(['btn btn-sm border', 'border-ink-900 bg-ink-900 text-white' => $tab === $key, 'border-stone-300 bg-white text-stone-700 hover:bg-stone-50' => $tab !== $key])>{{ $label }}</button>
                @endforeach
            </div>
        @endif
    </div>

    @if ($tab === 'setup')
        <div class="grid max-w-4xl gap-5 lg:grid-cols-2">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Company holidays</h2><span class="text-xs text-stone-500">From this year on</span></div>
                <form wire:submit="addHoliday" class="flex flex-wrap items-start gap-2 border-b border-stone-200 p-4">
                    <div><input type="date" class="input w-auto" wire:model="holiday.date" aria-label="Holiday date">@error('holiday.date') <p class="field-error max-w-40">{{ $message }}</p> @enderror</div>
                    <div class="min-w-40 flex-1"><input type="text" class="input" wire:model="holiday.name" maxlength="120" placeholder="e.g. Onam" aria-label="Holiday name">@error('holiday.name') <p class="field-error">{{ $message }}</p> @enderror</div>
                    <button type="submit" class="btn-primary">Add</button>
                </form>
                <ul class="divide-y divide-stone-100">
                    @forelse ($holidays as $h)
                        <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm" wire:key="holiday-{{ $h->id }}">
                            <span><span class="font-medium text-stone-900">{{ $h->name }}</span> <span class="text-stone-500">· {{ $h->date->format('D, d M Y') }}</span></span>
                            <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="deleteHoliday({{ $h->id }})" wire:confirm="Remove {{ $h->name }} from the holidays?">Remove</button>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-sm text-stone-500">No holidays added yet.</li>
                    @endforelse
                </ul>
            </section>

            <form wire:submit="saveSetup" class="card h-fit">
                <div class="card-header"><h2 class="card-title">Working week and leave</h2></div>
                <div class="space-y-4 p-4">
                    <x-field label="Weekly off days" name="setup.weekly_off" hint="These days are never counted as leave or as absent.">
                        <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
                            @foreach (\App\Support\WorkCalendar::WEEKDAYS as $number => $name)
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="rounded border-stone-300" value="{{ $number }}" wire:model="setup.weekly_off"> {{ $name }}</label>
                            @endforeach
                        </div>
                    </x-field>
                    <p class="text-sm text-stone-600">Leave allowances (CL, EL, RH, ML…) are set in <a class="link" href="{{ route('leave', ['tab' => 'types']) }}">Leave → Leave types</a>.</p>
                    <div class="flex justify-end"><button type="submit" class="btn-primary">Save</button></div>
                </div>
            </form>
        </div>
    @elseif ($tab === 'sheet')
        <div class="card">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-b border-stone-200 px-4 py-3 text-xs text-stone-600">
                @foreach (\App\Support\AttendanceSheet::CODES as $code => [$label, $class])
                    <span class="flex items-center gap-1.5"><span class="grid h-5 min-w-5 place-items-center rounded px-1 text-[10px] font-bold {{ $class }} {{ $code === '?' ? 'ring-1 ring-red-200 ring-inset' : '' }}">{{ $code }}</span> {{ $label }}</span>
                @endforeach
                <span class="ml-auto text-stone-500">Click a day to change it.</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-xs">
                    <thead>
                        <tr class="bg-stone-50 text-stone-500">
                            <th class="sticky left-0 z-10 min-w-44 border-b border-stone-200 bg-stone-50 px-3 py-2 text-left font-semibold uppercase">Person</th>
                            @foreach ($sheet->days as $day)
                                @php $date = $day->toDateString(); $off = isset($sheet->holidays[$date]) || \App\Support\WorkCalendar::isWeeklyOff($day); @endphp
                                <th @class(['border-b border-stone-200 px-0.5 py-1 text-center font-medium', 'bg-brand-50 text-brand-800' => $date === $today, 'text-stone-400' => $off]) title="{{ $day->format('l, d M') }}{{ isset($sheet->holidays[$date]) ? ' · '.$sheet->holidays[$date] : '' }}">
                                    <span class="block text-[10px] uppercase">{{ substr($day->format('D'), 0, 2) }}</span>
                                    @if ($date <= $today && ! $off)
                                        <button type="button" class="font-semibold hover:underline" wire:click="markDayPresent('{{ $date }}')" wire:confirm="Mark everyone not yet marked on {{ $day->format('d M') }} as present?" title="Mark everyone unmarked on this day as present">{{ $day->format('j') }}</button>
                                    @else <span class="font-semibold">{{ $day->format('j') }}</span> @endif
                                </th>
                            @endforeach
                            <th class="border-b border-l border-stone-200 px-2 py-2 text-center font-semibold" title="Days present">P</th>
                            <th class="border-b border-stone-200 px-2 py-2 text-center font-semibold" title="Days absent">A</th>
                            <th class="border-b border-stone-200 px-2 py-2 text-center font-semibold" title="Days on leave">L</th>
                            <th class="border-b border-stone-200 px-2 py-2 text-center font-semibold" title="Working days not marked">?</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr wire:key="row-{{ $user->id }}" class="hover:bg-stone-50/60">
                                <td class="sticky left-0 z-10 border-b border-stone-100 bg-white px-3 py-1.5 text-sm"><span class="flex items-center gap-2"><x-avatar :src="$user->avatarUrl()" :name="$user->name" size="size-6" class="text-[9px]" /> <span class="truncate">{{ $user->name }}</span></span></td>
                                @foreach ($sheet->days as $day)
                                    @php $date = $day->toDateString(); $c = $sheet->cells[$user->id][$date]; $style = \App\Support\AttendanceSheet::CODES[$c['code']][1] ?? ''; @endphp
                                    <td class="border-b border-stone-100 p-0.5 text-center">
                                        @if ($date <= $today)
                                            <button type="button" wire:click="editCell({{ $user->id }}, '{{ $date }}')" class="grid h-6 w-full min-w-6 cursor-pointer place-items-center rounded text-[10px] font-bold {{ $style }} {{ $c['code'] === '?' ? 'ring-1 ring-red-200 ring-inset' : '' }} hover:ring-2 hover:ring-brand-400"
                                                    title="{{ $user->name }} · {{ $day->format('D, d M') }} · {{ \App\Support\AttendanceSheet::CODES[$c['code']][0] ?? '' }}{{ $c['note'] ? ' · '.$c['note'] : '' }}">{{ $c['code'] }}</button>
                                        @else
                                            <span class="grid h-6 w-full min-w-6 place-items-center rounded text-[10px] font-bold {{ $style }}" title="{{ $c['note'] }}">{{ $c['code'] }}</span>
                                        @endif
                                    </td>
                                @endforeach
                                @php $t = $sheet->totals[$user->id]; $n = fn ($v) => rtrim(rtrim(number_format($v, 1), '0'), '.'); @endphp
                                <td class="border-b border-l border-stone-100 px-2 text-center text-sm font-semibold tabular-nums text-emerald-700">{{ $n($t['present']) }}</td>
                                <td class="border-b border-stone-100 px-2 text-center text-sm font-semibold tabular-nums {{ $t['absent'] ? 'text-red-700' : 'text-stone-400' }}">{{ $n($t['absent']) }}</td>
                                <td class="border-b border-stone-100 px-2 text-center text-sm font-semibold tabular-nums {{ $t['leave'] ? 'text-violet-700' : 'text-stone-400' }}">{{ $n($t['leave']) }}</td>
                                <td class="border-b border-stone-100 px-2 text-center text-sm font-semibold tabular-nums {{ $t['unmarked'] ? 'text-red-600' : 'text-stone-400' }}">{{ $t['unmarked'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ $sheet->days->count() + 5 }}" class="py-10 text-center text-sm text-stone-500">No active team members.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        @php $t = $sheet->totals[$me->id]; $n = fn ($v) => rtrim(rtrim(number_format($v, 1), '0'), '.'); @endphp
        <div class="mb-5 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <x-stat label="Days present" :value="$n($t['present'])" :hint="'of '.$t['working'].' working days in '.$sheet->start->format('F')" />
            <x-stat label="On leave" :value="$n($t['leave'])" hint="Approved leave" />
            <x-stat label="Absent" :value="$n($t['absent'])" :tone="$t['absent'] ? 'danger' : 'default'" hint="Marked absent" />
            <x-stat label="Not marked" :value="$t['unmarked']" :tone="$t['unmarked'] ? 'danger' : 'default'" hint="Past working days with no check-in" />
        </div>
        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Date</th><th>Status</th><th>Check in</th><th>Check out</th><th>Note</th></tr></thead>
                    <tbody>
                        @foreach ($sheet->days->filter(fn ($d) => $d->toDateString() <= $today)->reverse() as $day)
                            @php $c = $sheet->cells[$me->id][$day->toDateString()]; @endphp
                            <tr wire:key="my-{{ $day->toDateString() }}">
                                <td class="whitespace-nowrap">{{ $day->format('D, d M') }}</td>
                                <td><span class="inline-flex items-center gap-1.5"><span class="grid h-5 min-w-5 place-items-center rounded px-1 text-[10px] font-bold {{ \App\Support\AttendanceSheet::CODES[$c['code']][1] ?? '' }} {{ $c['code'] === '?' ? 'ring-1 ring-red-200 ring-inset' : '' }}">{{ $c['code'] }}</span> {{ \App\Support\AttendanceSheet::CODES[$c['code']][0] ?? '' }}</span></td>
                                <td class="tabular-nums">{{ $c['record']?->check_in ? \Illuminate\Support\Carbon::parse($c['record']->check_in)->format('g:i A') : '—' }}</td>
                                <td class="tabular-nums">{{ $c['record']?->check_out ? \Illuminate\Support\Carbon::parse($c['record']->check_out)->format('g:i A') : '—' }}</td>
                                <td class="max-w-72 truncate">{{ $c['note'] ?: '—' }}</td>
                            </tr>
                        @endforeach
                        @if ($sheet->start->toDateString() > $today) <x-empty colspan="5">This month has not started yet.</x-empty> @endif
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($cellPerson)
        <x-modal :title="$cellPerson->name.' · '.\Illuminate\Support\Carbon::parse($cellDate)->format('D, d M Y')" size="max-w-md">
            <form wire:submit="saveCell" class="space-y-4">
                <x-field label="Attendance" name="cell.status">
                    <select class="input" wire:model="cell.status">
                        <option value="">Clear (use leave, holiday or “not marked”)</option>
                        @foreach (\App\Models\AttendanceRecord::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Note" name="cell.note"><input class="input" type="text" wire:model="cell.note" maxlength="255" placeholder="Optional"></x-field>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
