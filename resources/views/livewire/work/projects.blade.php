<div>
    @unless ($clientId)
        <x-page-header title="Projects & Services" subtitle="Client projects and ongoing services, with their team, deadlines and the hours spent against the estimate.">
            @if ($canManage) <button type="button" class="btn-primary" wire:click="create"><x-icon name="plus" /> New project</button> @endif
        </x-page-header>
    @endunless

    <div class="card">
        <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
            @if ($clientId) <h2 class="card-title">Projects &amp; ongoing services</h2> @endif
            <input type="search" class="input w-full md:w-64" placeholder="Search…" wire:model.live.debounce.300ms="search" aria-label="Search projects">
            <select class="input w-auto" wire:model.live="status" aria-label="Status">
                <option value="open">Open</option>@foreach (\App\Models\Project::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach<option value="all">All</option>
            </select>
            <select class="input w-auto" wire:model.live="owner" aria-label="Owner"><option value="">Any owner</option>@foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach</select>
            @if ($clientId && $canManage) <button type="button" class="btn-primary btn-sm ml-auto" wire:click="create"><x-icon name="plus" /> New project</button> @endif
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Project / service</th>@unless ($clientId)<th>Client</th>@endunless<th>Owner</th><th>Status</th><th>Deadline</th><th>Hours</th><th>Next milestone</th><th>Quote</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($projects as $project)
                        <tr wire:key="project-{{ $project->id }}">
                            <td class="max-w-80"><a href="{{ route('projects.show', $project) }}" class="font-medium text-stone-900 hover:underline">{{ $project->name }}</a><p class="text-xs text-stone-500">{{ \App\Models\Project::KINDS[$project->kind] ?? $project->kind }}@if ($project->members->isNotEmpty()) · {{ $project->members->count() }} on the team @endif</p></td>
                            @unless ($clientId) <td><a class="link" href="{{ route('clients.show', ['client' => $project->client_id, 'tab' => 'projects']) }}">{{ $project->client->name }}</a></td> @endunless
                            <td>{{ $project->owner?->name ?? 'Unassigned' }}</td>
                            <td><x-status :value="['planned' => 'sent', 'active' => 'active', 'on_hold' => 'on_hold', 'completed' => 'done', 'cancelled' => 'void'][$project->status] ?? 'void'" :label="\App\Models\Project::STATUSES[$project->status] ?? $project->status" /></td>
                            <td class="whitespace-nowrap">@if ($project->isOverdue()) <span class="badge-red-solid">Overdue · {{ fmt_date($project->deadline) }}</span> @else {{ fmt_date($project->deadline) }} @endif</td>
                            @php $logged = (int) $project->logged_minutes; $used = $project->progress($logged); @endphp
                            <td class="min-w-32 whitespace-nowrap">
                                <span class="tabular-nums {{ $used !== null && $used > 100 ? 'font-semibold text-red-700' : 'text-stone-900' }}">{{ \App\Models\TimeEntry::format($logged) }}</span>@if ($project->estimated_hours !== null) <span class="text-xs text-stone-500">of {{ (float) $project->estimated_hours }}h</span> @endif
                                @if ($used !== null) <div class="mt-1 h-1.5 w-24 rounded-full bg-stone-100"><div class="h-1.5 rounded-full {{ $used > 100 ? 'bg-red-600' : 'bg-brand-500' }}" style="width: {{ min(100, $used) }}%"></div></div> @endif
                            </td>
                            <td class="max-w-64">{{ $project->next_milestone ?: '—' }}@if ($project->next_milestone_date)<div @class(['text-xs', 'font-semibold text-red-700' => days_until($project->next_milestone_date) < 0 && in_array($project->status, ['planned', 'active']), 'text-stone-500' => ! (days_until($project->next_milestone_date) < 0)])>{{ fmt_date($project->next_milestone_date) }}</div>@endif</td>
                            <td class="whitespace-nowrap">@if ($project->quote) @can('sales.manage') <a class="link font-mono text-xs" href="{{ route('quotes.show', $project->quote) }}">{{ $project->quote->number }}</a> @else <span class="font-mono text-xs">{{ $project->quote->number }}</span> @endcan @else — @endif</td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('projects.show', $project) }}" class="btn-ghost btn-sm">Open</a>
                                @if ($canManage)
                                    <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $project->id }})">Edit</button>
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $project->id }})" wire:confirm="Delete {{ $project->name }}?">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="$clientId ? 8 : 9">No projects or services here.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($projects->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $projects->links() }}</div> @endif
    </div>

    @if ($tracked)
        <section class="card mt-5">
            <div class="card-header"><h2 class="card-title">Time tracked by each person</h2><a href="{{ route('timesheet') }}" class="link text-xs">Open the tracker</a></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Person</th><th class="num">Today</th><th class="num">This week</th><th class="num">This month</th><th class="num">All time</th><th>Where the time went this month</th></tr></thead>
                    <tbody>
                        @forelse ($tracked['people'] as $person)
                            <tr wire:key="tracked-{{ $person['id'] }}">
                                <td class="whitespace-nowrap">
                                    <span class="font-medium text-stone-900">{{ $person['name'] }}</span>
                                    @if ($person['running']) <span class="badge-green ml-1" title="Timer running on {{ $person['running']->project?->name }} since {{ $person['running']->started_at->timezone(crm_tz())->format('g:i A') }}"><span class="size-1.5 rounded-full bg-emerald-600"></span> Working now</span> @endif
                                </td>
                                <td class="num">{{ $person['today'] ? \App\Models\TimeEntry::format($person['today']) : '—' }}</td>
                                <td class="num">{{ $person['week'] ? \App\Models\TimeEntry::format($person['week']) : '—' }}</td>
                                <td class="num font-medium text-stone-900">{{ $person['month'] ? \App\Models\TimeEntry::format($person['month']) : '—' }}</td>
                                <td class="num">{{ \App\Models\TimeEntry::format($person['total']) }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1.5">
                                        @forelse ($person['projects'] as $row)
                                            <a href="{{ route('projects.show', $row->project_id) }}" class="badge-gray hover:bg-stone-200">{{ str($row->name)->limit(26) }} · {{ \App\Models\TimeEntry::format($row->minutes) }}</a>
                                        @empty <span class="text-stone-400">—</span> @endforelse
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-empty colspan="6">Nobody has tracked time yet. Time is added from Team → Timesheet.</x-empty>
                        @endforelse
                    </tbody>
                    @if ($tracked['people']->count() > 1)
                        <tfoot><tr class="bg-stone-50 font-semibold text-stone-900"><td class="px-3 py-2.5">Everyone</td><td class="num px-3">{{ \App\Models\TimeEntry::format($tracked['totals']['today']) }}</td><td class="num px-3">{{ \App\Models\TimeEntry::format($tracked['totals']['week']) }}</td><td class="num px-3">{{ \App\Models\TimeEntry::format($tracked['totals']['month']) }}</td><td class="num px-3">{{ \App\Models\TimeEntry::format($tracked['totals']['total']) }}</td><td></td></tr></tfoot>
                    @endif
                </table>
            </div>
        </section>
    @endif

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit project' : 'New project or service'" size="max-w-3xl">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                @unless ($clientId)
                    <x-field label="Client" name="form.client_id" required class="md:col-span-2">
                        <select class="input" wire:model.live="form.client_id"><option value="">Select client…</option>@foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach</select>
                    </x-field>
                @endunless
                <x-field label="Name" name="form.name" required><input class="input" type="text" wire:model="form.name" placeholder="e.g. Website redesign"></x-field>
                <x-field label="Type" name="form.kind"><select class="input" wire:model="form.kind">@foreach (\App\Models\Project::KINDS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select></x-field>
                <x-field label="Owner" name="form.owner_id"><select class="input" wire:model="form.owner_id"><option value="">Unassigned</option>@foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach</select></x-field>
                <x-field label="Status" name="form.status"><select class="input" wire:model="form.status">@foreach (\App\Models\Project::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select></x-field>
                <x-field label="Description" name="form.description" class="md:col-span-2" hint="What the work covers. Bold, bullet lists and links are kept.">
                    <x-editor model="form.description" :value="$form['description']" min-height="7rem" label="Project description" />
                </x-field>
                <x-field label="Start date" name="form.start_date"><input class="input" type="date" wire:model="form.start_date"></x-field>
                <x-field label="Deadline" name="form.deadline"><input class="input" type="date" wire:model="form.deadline"></x-field>
                <x-field label="Estimated hours" name="form.estimated_hours" hint="Hours quoted or planned. Time logged is compared with this."><input class="input" type="number" step="0.5" min="0" wire:model="form.estimated_hours" placeholder="e.g. 80"></x-field>
                <x-field label="Accepted quote" name="form.quote_id" hint="Optional link to the quote this work came from.">
                    <select class="input" wire:model="form.quote_id" @disabled($quotes->isEmpty())><option value="">Not linked</option>@foreach ($quotes as $q) <option value="{{ $q->id }}">{{ $q->number }} · {{ $q->title }}</option> @endforeach</select>
                </x-field>
                <x-field label="Next milestone" name="form.next_milestone"><input class="input" type="text" wire:model="form.next_milestone" placeholder="e.g. Homepage design sign-off"></x-field>
                <x-field label="Milestone date" name="form.next_milestone_date"><input class="input" type="date" wire:model="form.next_milestone_date"></x-field>
                <x-field label="Team" name="members" class="md:col-span-2" hint="Who works on it. They can log time to it. Leave everyone unticked to let anyone log time.">
                    <div class="grid max-h-40 gap-x-4 gap-y-1.5 overflow-y-auto rounded-md border border-stone-200 p-3 sm:grid-cols-2 md:grid-cols-3">
                        @foreach ($users as $u)
                            <label class="flex items-center gap-2 text-sm" wire:key="member-{{ $u->id }}"><input type="checkbox" class="rounded border-stone-300" value="{{ $u->id }}" wire:model="members"> <span class="truncate">{{ $u->name }}</span></label>
                        @endforeach
                    </div>
                    @error('members.*') <p class="field-error">{{ $message }}</p> @enderror
                </x-field>
                <x-field label="Internal notes" name="form.notes" class="md:col-span-2"><textarea class="input" rows="2" wire:model="form.notes"></textarea></x-field>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
