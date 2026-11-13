<div>
    <div class="mb-1 text-sm"><a href="{{ route('projects.index') }}" class="link">← Projects &amp; Services</a></div>
    <x-page-header :title="$project->name" :subtitle="$project->client->name.' · '.(\App\Models\Project::KINDS[$project->kind] ?? $project->kind)">
        <x-status :value="['planned' => 'sent', 'active' => 'active', 'on_hold' => 'on_hold', 'completed' => 'done', 'cancelled' => 'void'][$project->status] ?? 'void'" :label="\App\Models\Project::STATUSES[$project->status] ?? $project->status" />
        @if ($canLog) <a href="{{ route('timesheet', ['project' => $project->id, 'add' => 1]) }}" class="btn-primary"><x-icon name="plus" /> Log time</a> @endif
    </x-page-header>

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Time logged" :value="\App\Models\TimeEntry::format($logged)" :hint="$billable !== $logged ? \App\Models\TimeEntry::format($billable).' billable' : 'All billable'" />
        <x-stat label="Estimate" :value="$project->estimated_hours !== null ? (float) $project->estimated_hours.'h' : 'Not set'" hint="Hours quoted or planned" />
        <x-stat label="Used" :value="$used !== null ? $used.'%' : '—'" :tone="$used !== null && $used > 100 ? 'danger' : 'default'"
                :hint="$used === null ? 'Add an estimate to compare' : ($used > 100 ? \App\Models\TimeEntry::format($logged - (float) $project->estimated_hours * 60).' over the estimate' : \App\Models\TimeEntry::format((float) $project->estimated_hours * 60 - $logged).' left')" />
        <x-stat label="Deadline" :value="fmt_date($project->deadline, 'Not set')" :tone="$project->isOverdue() ? 'danger' : 'default'" :hint="$project->isOverdue() ? 'Overdue' : ($project->start_date ? 'Started '.fmt_date($project->start_date) : '')" />
    </div>

    @if ($used !== null)
        <div class="card mb-5 p-4">
            <div class="flex items-baseline justify-between text-sm"><span class="font-semibold text-stone-900">Hours spent against the estimate</span><span class="tabular-nums text-stone-600">{{ \App\Models\TimeEntry::format($logged) }} of {{ (float) $project->estimated_hours }}h</span></div>
            <div class="mt-2 h-3 rounded-full bg-stone-100" role="img" aria-label="{{ $used }}% of the estimated hours used"><div class="h-3 rounded-full {{ $used > 100 ? 'bg-red-600' : ($used > 85 ? 'bg-amber-500' : 'bg-brand-500') }}" style="width: {{ min(100, $used) }}%"></div></div>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-5">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Description</h2></div>
                <div class="p-4 text-sm text-stone-700">
                    @if ($project->description) <div class="rich">{{ rich($project->description) }}</div>
                    @else <p class="text-stone-500">No description yet.{{ $canManage ? ' Add one with Edit on the projects list.' : '' }}</p> @endif
                </div>
            </section>

            @if ($canTasks)
                <div id="tasks" class="scroll-mt-20"></div>
                <livewire:tasks.index :project-id="$project->id" :key="'tasks-project-'.$project->id" />
            @endif

            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ $manager ? 'Time entries' : 'My time on this project' }}</h2><a href="{{ route('timesheet', ['project' => $project->id]) }}" class="link text-xs">Open in Timesheet</a></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Date</th>@if ($manager)<th>Person</th>@endif<th>What was done</th><th class="num">Time</th></tr></thead>
                        <tbody>
                            @forelse ($recent as $entry)
                                <tr wire:key="entry-{{ $entry->id }}">
                                    <td class="whitespace-nowrap">{{ fmt_date($entry->work_date) }}</td>
                                    @if ($manager) <td>{{ $entry->user?->name ?? '—' }}</td> @endif
                                    <td class="max-w-96">{{ $entry->description ?: '—' }} @unless ($entry->is_billable) <span class="badge-gray ml-1">Not billable</span> @endunless</td>
                                    <td class="num">{{ \App\Models\TimeEntry::format($entry->minutes) }}</td>
                                </tr>
                            @empty
                                <x-empty :colspan="$manager ? 4 : 3">No time logged yet.</x-empty>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="space-y-5">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Details</h2></div>
                <dl class="space-y-3 p-4 text-sm">
                    <div><dt class="label">Client</dt><dd><a class="link" href="{{ route('clients.show', ['client' => $project->client_id, 'tab' => 'projects']) }}">{{ $project->client->name }}</a></dd></div>
                    <div><dt class="label">Project owner</dt><dd>{{ $project->owner?->name ?? 'Unassigned' }}</dd></div>
                    <div><dt class="label">Tasks</dt><dd>{{ $tasksOpen }} open · {{ $tasksDone }} done</dd></div>
                    <div><dt class="label">Start</dt><dd>{{ fmt_date($project->start_date) }}</dd></div>
                    <div><dt class="label">Next milestone</dt><dd>{{ $project->next_milestone ?: '—' }}@if ($project->next_milestone_date) <span class="text-stone-500">· {{ fmt_date($project->next_milestone_date) }}</span>@endif</dd></div>
                    @if ($project->quote) <div><dt class="label">Quote</dt><dd class="font-mono">{{ $project->quote->number }}</dd></div> @endif
                    @if ($project->notes) <div><dt class="label">Internal notes</dt><dd class="whitespace-pre-line text-stone-700">{{ $project->notes }}</dd></div> @endif
                </dl>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Working on it</h2><span class="text-xs text-stone-500">{{ $project->members->count() ?: 'Open to everyone' }}</span></div>
                <div class="p-4">
                    @forelse ($project->members as $member)
                        <div class="flex items-center gap-2.5 py-1.5 text-sm" wire:key="member-{{ $member->id }}"><x-avatar :src="$member->avatarUrl()" :name="$member->name" size="size-7" class="text-[10px]" /> <span class="truncate">{{ $member->name }}</span></div>
                    @empty
                        <p class="text-sm text-stone-500">No fixed team, so anyone can log time to this project.</p>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Hours by person</h2></div>
                @php $top = max(1, (int) $byPerson->max('minutes')); @endphp
                <div class="space-y-3 p-4">
                    @forelse ($byPerson as $row)
                        <div class="text-sm">
                            <div class="flex items-baseline justify-between gap-3"><span class="truncate text-stone-700">{{ $row->name }}</span><span class="font-medium tabular-nums text-stone-900">{{ \App\Models\TimeEntry::format($row->minutes) }}</span></div>
                            <div class="mt-1 h-2 rounded-full bg-stone-100"><div class="h-2 rounded-full bg-brand-500" style="width: {{ max(4, round($row->minutes / $top * 100)) }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-sm text-stone-500">Nobody has logged time yet.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
