<div>
    <x-page-header title="Timesheet" :subtitle="$manager ? 'Hours logged against projects. Yours, or anyone on the team.' : 'Log the hours you spend on each project.'">
        @if ($tab === 'tracker') <a href="{{ route('timesheet.export', array_filter(['week' => $week, 'person' => $person, 'project' => $project])) }}" class="btn-secondary"><x-icon name="download" /> Export week</a> @endif
        <button type="button" class="btn-secondary" wire:click="create"><x-icon name="plus" /> Add time by hand</button>
    </x-page-header>

    <div class="mb-5 flex gap-6 border-b border-stone-200" role="tablist">
        <button type="button" role="tab" @class(['tab', 'tab-active' => $tab === 'tracker']) wire:click="$set('tab', 'tracker')">Tracker</button>
        <button type="button" role="tab" @class(['tab', 'tab-active' => $tab === 'overview']) wire:click="$set('tab', 'overview')">Overview</button>
    </div>

    @if ($tab === 'overview')
        @php $clock = fn ($seconds) => \App\Models\TimeEntry::clock($seconds); @endphp
        <div class="mb-4 flex flex-wrap items-center gap-2 print:hidden">
            <a href="{{ route('timesheet.export', $this->overviewFilters()) }}" class="btn-primary"><x-icon name="download" /> Export data</a>
            <button type="button" class="btn-primary" x-data x-on:click="window.print()">Print</button>
            <div class="ml-auto flex items-center gap-2 text-xs text-stone-600">
                Showing
                <select class="input w-auto py-1 text-xs" wire:model.live.number="perPage" aria-label="Rows per page">@foreach ([10, 25, 50, 100] as $n) <option value="{{ $n }}">{{ $n }}</option> @endforeach</select>
                of {{ $overview['rows']->total() }} results
            </div>
        </div>

        <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-6 print:hidden">
            <label class="block text-xs text-stone-600">Task name<input type="search" class="input mt-1" wire:model.live.debounce.300ms="task" placeholder="Task name…"></label>
            <label class="block text-xs text-stone-600">Client
                <select class="input mt-1" wire:model.live="client"><option value="">All clients</option>@foreach ($overview['clients'] as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach</select>
            </label>
            <label class="block text-xs text-stone-600">Project
                <select class="input mt-1" wire:model.live="project"><option value="">All projects</option>@foreach ($filterProjects as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach</select>
            </label>
            @if ($manager)
                <label class="block text-xs text-stone-600">Person
                    <select class="input mt-1" wire:model.live="person"><option value="">My time</option><option value="all">Everyone</option>@foreach ($people as $p) @if ($p->id !== auth()->id()) <option value="{{ $p->id }}">{{ $p->name }}</option> @endif @endforeach</select>
                </label>
            @endif
            <label class="block text-xs text-stone-600">From<input type="date" class="input mt-1" wire:model.live="from"></label>
            <label class="block text-xs text-stone-600">To<input type="date" class="input mt-1" wire:model.live="to"></label>
            <div class="flex flex-wrap items-end gap-1.5 sm:col-span-2 lg:col-span-6">
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month', 'last_month' => 'Last month'] as $key => $label)
                    <button type="button" class="btn-ghost btn-sm border border-stone-200" wire:click="setRange('{{ $key }}')">{{ $label }}</button>
                @endforeach
                <span class="ml-1 text-xs text-stone-500">{{ fmt_date($overview['from']) }} to {{ fmt_date($overview['to']) }} · {{ $viewing }}</span>
            </div>
        </div>

        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            @foreach (['Total hours' => $overview['total'], 'Total billable' => $overview['billable'], 'Total non-billable' => $overview['nonBillable']] as $label => $seconds)
                <div class="card p-5">
                    <p class="text-sm font-semibold text-stone-700">{{ $label }}</p>
                    <p class="mt-3 font-mono text-3xl font-bold tabular-nums text-stone-900">{{ $clock($seconds) }}</p>
                </div>
            @endforeach
        </div>

        <div class="mb-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <section class="card p-5">
                <h2 class="card-title mb-4">Hours per day</h2>
                @php $peak = $overview['peak']; $many = $overview['bars']->count() > 16; @endphp
                <div class="flex gap-3">
                    <div class="flex h-48 flex-col justify-between text-right text-[10px] tabular-nums text-stone-400"><span>{{ rtrim(rtrim(number_format($peak / 3600, 2), '0'), '.') }}</span><span>{{ rtrim(rtrim(number_format($peak / 7200, 2), '0'), '.') }}</span><span>0</span></div>
                    <div class="min-w-0 flex-1 overflow-x-auto">
                        <div class="flex h-48 items-end gap-1 border-b border-stone-200 bg-[linear-gradient(to_bottom,transparent_calc(50%-1px),#e7e5e4_calc(50%-1px),#e7e5e4_50%,transparent_50%)]" style="min-width: {{ $overview['bars']->count() * 22 }}px;" role="img" aria-label="Hours per day: {{ $overview['bars']->filter(fn ($b) => $b['seconds'])->map(fn ($b) => $b['label'].' '.$clock($b['seconds']))->implode(', ') ?: 'nothing tracked' }}">
                            @foreach ($overview['bars'] as $bar)
                                <div class="group flex h-full flex-1 flex-col justify-end" title="{{ $bar['label'] }}: {{ $clock($bar['seconds']) }}">
                                    <div class="mx-auto w-3 rounded-t {{ $bar['seconds'] ? 'bg-slate-400 group-hover:bg-brand-600' : '' }}" style="height: {{ $bar['seconds'] ? max(2, round($bar['seconds'] / $peak * 100)) : 0 }}%"></div>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex gap-1 pt-1.5" style="min-width: {{ $overview['bars']->count() * 22 }}px;">
                            @foreach ($overview['bars'] as $index => $bar)
                                <span class="flex h-14 flex-1 justify-center overflow-visible text-[10px] whitespace-nowrap text-stone-500"><span class="origin-top-right -translate-x-1/2 -rotate-45">{{ ! $many || $index % 2 === 0 ? $bar['label'] : '' }}</span></span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            <section class="card p-5">
                <div class="mb-3 flex items-baseline justify-between"><h2 class="card-title">Summary</h2><span class="font-mono text-base font-bold tabular-nums text-stone-900">{{ $clock($overview['total']) }}</span></div>
                <div class="flex flex-col items-center gap-4">
                    <svg viewBox="0 0 42 42" class="size-44 -rotate-90" role="img" aria-label="Time by project: {{ $overview['slices']->map(fn ($s) => $s['name'].' '.$clock($s['seconds']))->implode(', ') ?: 'nothing tracked' }}">
                        <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#e7e5e4" stroke-width="5.5" />
                        @foreach ($overview['slices'] as $slice)
                            <circle cx="21" cy="21" r="15.9155" fill="none" stroke="{{ $slice['color'] }}" stroke-width="5.5" stroke-dasharray="{{ max(0, $slice['percent'] - ($overview['slices']->count() > 1 ? 0.5 : 0)) }} 100" stroke-dashoffset="-{{ $slice['offset'] }}"><title>{{ $slice['name'] }}: {{ $clock($slice['seconds']) }} ({{ round($slice['percent']) }}%)</title></circle>
                        @endforeach
                    </svg>
                    <ul class="w-full space-y-1.5 text-sm">
                        @forelse ($overview['slices'] as $slice)
                            <li class="flex items-center gap-2"><span class="size-2.5 shrink-0 rounded-sm" style="background: {{ $slice['color'] }}"></span><span class="min-w-0 flex-1 truncate text-stone-700">{{ $slice['name'] }}</span><span class="font-mono text-xs tabular-nums text-stone-900">{{ $clock($slice['seconds']) }}</span><span class="w-9 text-right text-xs tabular-nums text-stone-500">{{ round($slice['percent']) }}%</span></li>
                        @empty
                            <li class="text-center text-stone-500">Nothing tracked in these dates.</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Task</th>@if ($person !== '')<th>Person</th>@endif<th>Client</th><th>Project</th><th>Duration</th><th>Date</th><th>Billable</th></tr></thead>
                    <tbody>
                        @forelse ($overview['rows'] as $entry)
                            <tr wire:key="row-{{ $entry->id }}">
                                <td class="max-w-80 truncate text-stone-900">{{ $entry->description ?: '—' }}</td>
                                @if ($person !== '') <td>{{ $entry->user?->name ?? '—' }}</td> @endif
                                <td class="max-w-48 truncate">{{ $entry->project?->client?->name ?? '—' }}</td>
                                <td class="max-w-56 truncate"><a class="hover:underline" href="{{ route('projects.show', $entry->project_id) }}">{{ $entry->project?->name ?? '—' }}</a></td>
                                <td class="font-mono tabular-nums">{{ $clock($entry->seconds()) }}</td>
                                <td class="whitespace-nowrap">{{ $entry->work_date->format('F j, Y') }}</td>
                                <td><span class="{{ $entry->is_billable ? 'badge-green' : 'badge-gray' }}">{{ $entry->is_billable ? 'Yes' : 'No' }}</span></td>
                            </tr>
                        @empty
                            <x-empty :colspan="$person !== '' ? 7 : 6">No time entries match these filters.</x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($overview['rows']->hasPages()) <div class="border-t border-stone-200 px-4 py-3 print:hidden">{{ $overview['rows']->links() }}</div> @endif
        </div>
    @else
        <div class="mb-5 flex flex-wrap items-center gap-2">
            <button type="button" class="btn-secondary" wire:click="shift(-1)" aria-label="Previous week">←</button>
            <span class="min-w-56 text-center text-sm font-semibold text-stone-900">{{ $start->format('d M') }} – {{ $end->format('d M Y') }}</span>
            <button type="button" class="btn-secondary" wire:click="shift(1)" aria-label="Next week">→</button>
            <button type="button" class="btn-ghost border border-stone-200" wire:click="thisWeek">This week</button>
            @if ($manager)
                <select class="input ml-auto w-auto" wire:model.live="person" aria-label="Whose time">
                    <option value="">My time</option>
                    <option value="all">Everyone</option>
                    @foreach ($people as $p) @if ($p->id !== auth()->id()) <option value="{{ $p->id }}">{{ $p->name }}</option> @endif @endforeach
                </select>
            @endif
            <select class="input w-auto {{ $manager ? '' : 'ml-auto' }}" wire:model.live="project" aria-label="Project">
                <option value="">All projects</option>
                @foreach ($filterProjects as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
            </select>
        </div>

        <div class="mb-5 grid gap-4 sm:grid-cols-3">
            <x-stat :label="$viewing.' · this week'" :value="\App\Models\TimeEntry::format($total)" :hint="$entries->count().' '.str('entry')->plural($entries->count())" />
            <x-stat label="Billable" :value="\App\Models\TimeEntry::format($billable)" :hint="$total ? round($billable / $total * 100).'% of the week' : 'Nothing logged'" />
            <x-stat label="Projects worked on" :value="$byProject->count()" :hint="$byProject->first()?->name ? 'Most: '.$byProject->first()->name : ''" />
        </div>

        {{-- Timer / Manual switch --}}
        <div class="mb-3 flex gap-2" role="group" aria-label="How to add time">
            <button type="button" wire:click="$set('mode', 'manual')" @class(['inline-flex cursor-pointer items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium', 'border-brand-600 bg-brand-600 text-white' => $mode === 'manual', 'border-stone-300 bg-white text-stone-600 hover:bg-stone-50' => $mode !== 'manual'])><x-icon name="menu" class="size-4" /> Manual</button>
            <button type="button" wire:click="$set('mode', 'timer')" @class(['inline-flex cursor-pointer items-center gap-2 rounded-full border px-4 py-2 text-sm font-medium', 'border-brand-600 bg-brand-600 text-white' => $mode === 'timer', 'border-stone-300 bg-white text-stone-600 hover:bg-stone-50' => $mode !== 'timer'])><x-icon name="clock" class="size-4" /> Timer</button>
        </div>

        @if ($running)
            {{-- A timer is running: the clock counts in the browser from the saved start time, so it survives a reload. --}}
            <div class="card mb-5 flex flex-wrap items-center gap-3 border-emerald-300 bg-emerald-50 p-3" wire:key="running-{{ $running->id }}"
                 x-data="{ started: {{ $running->started_at->getTimestamp() * 1000 }}, now: Date.now(), clock() { const s = Math.max(0, Math.floor((this.now - this.started) / 1000)); return [Math.floor(s / 3600), Math.floor(s % 3600 / 60), s % 60].map(n => String(n).padStart(2, '0')).join(':') } }"
                 x-init="setInterval(() => now = Date.now(), 1000)">
                <span class="relative flex size-3 shrink-0"><span class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500 opacity-60"></span><span class="relative inline-flex size-3 rounded-full bg-emerald-600"></span></span>
                <div class="min-w-48 flex-1">
                    <p class="truncate text-sm font-semibold text-stone-900">{{ $running->description ?: 'Working…' }}</p>
                    <p class="truncate text-xs text-stone-600">{{ $running->project?->name }} · started {{ $running->started_at->timezone(crm_tz())->format('g:i A') }}</p>
                </div>
                <span class="font-mono text-xl font-semibold tabular-nums text-stone-900" x-text="clock()">{{ \App\Models\TimeEntry::clock($running->seconds()) }}</span>
                <button type="button" class="rounded-lg bg-red-600 px-6 py-2.5 text-sm font-bold tracking-wide text-white uppercase hover:bg-red-700" wire:click="stopTimer">Stop</button>
                <button type="button" class="btn-ghost btn-sm" wire:click="discardTimer" wire:confirm="Discard this timer without saving the time?">Discard</button>
            </div>
        @elseif ($mode === 'timer')
            <form wire:submit="startTimer" class="card mb-5 flex flex-wrap items-start gap-3 p-3">
                <div class="min-w-56 flex-1"><input class="input" type="text" wire:model="timer.description" maxlength="500" placeholder="You’re working on…" aria-label="What you are working on">@error('timer.description') <p class="field-error">{{ $message }}</p> @enderror</div>
                <div class="w-full sm:w-64">
                    <select class="input" wire:model="timer.project_id" aria-label="Project">
                        <option value="">+ Project</option>
                        @foreach ($projects as $p) <option value="{{ $p->id }}">{{ $p->name }} · {{ $p->client?->name }}</option> @endforeach
                    </select>
                    @error('timer.project_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <label class="flex cursor-pointer items-center gap-1.5 py-2 text-sm text-stone-600" title="Billable to the client"><input type="checkbox" class="rounded border-stone-300" wire:model="timer.is_billable"> Billable</label>
                <span class="py-2 font-mono text-lg font-semibold tabular-nums text-stone-400">00:00:00</span>
                <button type="submit" class="rounded-lg bg-emerald-600 px-7 py-2.5 text-sm font-bold tracking-wide text-white uppercase hover:bg-emerald-700" wire:loading.attr="disabled">Start</button>
            </form>
        @else
            <form wire:submit="quickAdd" class="card mb-5 flex flex-wrap items-start gap-3 p-3">
                <div class="min-w-56 flex-1"><input class="input" type="text" wire:model="quick.description" maxlength="500" placeholder="What did you work on?" aria-label="What you worked on">@error('quick.description') <p class="field-error">{{ $message }}</p> @enderror</div>
                <div class="w-full sm:w-56">
                    <select class="input" wire:model="quick.project_id" aria-label="Project">
                        <option value="">+ Project</option>
                        @foreach ($projects as $p) <option value="{{ $p->id }}">{{ $p->name }} · {{ $p->client?->name }}</option> @endforeach
                    </select>
                    @error('quick.project_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div><input class="input w-auto" type="date" wire:model="quick.work_date" max="{{ today_local()->toDateString() }}" aria-label="Date">@error('quick.work_date') <p class="field-error max-w-44">{{ $message }}</p> @enderror</div>
                <div><input class="input w-24" type="number" step="0.25" min="0.25" max="24" wire:model="quick.hours" placeholder="Hours" aria-label="Hours">@error('quick.hours') <p class="field-error max-w-44">{{ $message }}</p> @enderror</div>
                <label class="flex cursor-pointer items-center gap-1.5 py-2 text-sm text-stone-600"><input type="checkbox" class="rounded border-stone-300" wire:model="quick.is_billable"> Billable</label>
                <button type="submit" class="rounded-lg bg-brand-600 px-7 py-2.5 text-sm font-bold tracking-wide text-white uppercase hover:bg-brand-700">Add</button>
            </form>
        @endif

        <div class="mb-3 flex items-baseline justify-between">
            <h2 class="text-base font-medium text-stone-700">{{ $start->toDateString() <= today_local()->toDateString() && $end->toDateString() >= today_local()->toDateString() ? 'This week' : 'Week of '.$start->format('d M') }} <span class="text-sm text-stone-500">· {{ $viewing }}</span></h2>
            <p class="text-base text-stone-700">Week total: <span class="font-mono font-semibold tabular-nums text-stone-900">{{ \App\Models\TimeEntry::clock($weekSeconds) }}</span></p>
        </div>

        @forelse ($groups as $date => $group)
            <section class="card mb-4 overflow-hidden" wire:key="group-{{ $date }}">
                <div class="flex items-center gap-4 border-b border-stone-200 bg-stone-50 px-4 py-3">
                    <h3 class="text-base font-semibold text-stone-900">{{ $group['label'] }}</h3>
                    <span class="border-l border-stone-300 pl-4 font-mono text-base tabular-nums text-stone-600">{{ \App\Models\TimeEntry::clock($group['seconds']) }}</span>
                </div>
                <div class="space-y-2 p-3">
                    @foreach ($group['rows'] as $entry)
                        @php $mine = $entry->user_id === auth()->id(); $canEdit = $manager || ($mine && $entry->work_date->toDateString() >= $editLimit); @endphp
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg border border-stone-200 bg-white px-4 py-2.5" wire:key="entry-{{ $entry->id }}">
                            <p class="min-w-48 flex-1 truncate text-sm text-stone-900">{{ $entry->description ?: '—' }}@if ($person !== '') <span class="ml-2 text-xs text-stone-500">{{ $entry->user?->name }}</span>@endif</p>
                            <a href="{{ route('projects.show', $entry->project_id) }}" class="max-w-64 truncate text-sm font-medium text-brand-700 hover:underline" title="{{ $entry->project?->client?->name }}">{{ $entry->project?->name ?? '—' }}</a>
                            <span class="text-sm font-bold {{ $entry->is_billable ? 'text-brand-600' : 'text-stone-300' }}" title="{{ $entry->is_billable ? 'Billable' : 'Not billable' }}">{{ setting('currency', 'INR') === 'INR' ? '₹' : '$' }}</span>
                            <span class="w-40 border-l border-stone-200 pl-4 text-sm tabular-nums text-stone-600">{{ $entry->started_at ? $entry->started_at->timezone(crm_tz())->format('g:i A').' – '.$entry->ended_at?->timezone(crm_tz())->format('g:i A') : 'Added by hand' }}</span>
                            <span class="w-20 border-l border-stone-200 pl-4 font-mono text-sm font-semibold tabular-nums text-stone-900">{{ \App\Models\TimeEntry::clock($entry->seconds()) }}</span>
                            <span class="flex items-center border-l border-stone-200 pl-2">
                                @if ($mine && ! $running) <button type="button" class="btn-ghost btn-sm" wire:click="restart({{ $entry->id }})" title="Start the timer again for this work" aria-label="Start the timer again for this work">▶</button> @endif
                                @if ($canEdit)
                                    <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $entry->id }})">Edit</button>
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $entry->id }})" wire:confirm="Delete this time entry?">Delete</button>
                                @else <span class="px-2 text-xs text-stone-400">Locked</span> @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="card p-10 text-center text-sm text-stone-500">No time tracked in this week. Press Start on the timer, or switch to Manual to add finished work.</div>
        @endforelse
    @endif

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit time entry' : 'Log time'" size="max-w-xl">
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <x-field label="Date" name="form.work_date" required><input class="input" type="date" wire:model="form.work_date" max="{{ today_local()->toDateString() }}" @unless ($manager) min="{{ $editLimit }}" @endunless></x-field>
                <x-field label="Hours" name="form.hours" required hint="Use decimals: 1.5 = 1 hour 30 minutes."><input class="input" type="number" step="0.25" min="0.25" max="24" wire:model="form.hours" placeholder="e.g. 2.5"></x-field>
                <x-field label="Project" name="form.project_id" required class="sm:col-span-2" :hint="$projects->isEmpty() ? 'You are not on any open project yet. Ask an admin to add you to one.' : null">
                    <select class="input" wire:model="form.project_id">
                        <option value="">Select a project…</option>
                        @foreach ($projects as $p) <option value="{{ $p->id }}">{{ $p->name }} · {{ $p->client?->name }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="What did you work on?" name="form.description" class="sm:col-span-2"><textarea class="input" rows="2" wire:model="form.description" maxlength="500" placeholder="e.g. Home page design, client feedback round 2"></textarea></x-field>
                <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" class="rounded border-stone-300" wire:model="form.is_billable"> Billable to the client</label>
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
