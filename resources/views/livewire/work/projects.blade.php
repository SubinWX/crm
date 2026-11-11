<div>
    @unless ($clientId)
        <x-page-header title="Projects & Services" subtitle="Client projects and ongoing services, with owners, deadlines and the next milestone.">
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
                <thead><tr><th>Project / service</th>@unless ($clientId)<th>Client</th>@endunless<th>Owner</th><th>Status</th><th>Deadline</th><th>Next milestone</th><th>Quote</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($projects as $project)
                        <tr wire:key="project-{{ $project->id }}">
                            <td class="max-w-80"><p class="font-medium text-stone-900">{{ $project->name }}</p><p class="text-xs text-stone-500">{{ \App\Models\Project::KINDS[$project->kind] ?? $project->kind }}</p></td>
                            @unless ($clientId) <td><a class="link" href="{{ route('clients.show', ['client' => $project->client_id, 'tab' => 'projects']) }}">{{ $project->client->name }}</a></td> @endunless
                            <td>{{ $project->owner?->name ?? 'Unassigned' }}</td>
                            <td><x-status :value="['planned' => 'sent', 'active' => 'active', 'on_hold' => 'on_hold', 'completed' => 'done', 'cancelled' => 'void'][$project->status] ?? 'void'" :label="\App\Models\Project::STATUSES[$project->status] ?? $project->status" /></td>
                            <td class="whitespace-nowrap">@if ($project->isOverdue()) <span class="badge-red-solid">Overdue · {{ fmt_date($project->deadline) }}</span> @else {{ fmt_date($project->deadline) }} @endif</td>
                            <td class="max-w-64">{{ $project->next_milestone ?: '—' }}@if ($project->next_milestone_date)<div @class(['text-xs', 'font-semibold text-red-700' => days_until($project->next_milestone_date) < 0 && in_array($project->status, ['planned', 'active']), 'text-stone-500' => ! (days_until($project->next_milestone_date) < 0)])>{{ fmt_date($project->next_milestone_date) }}</div>@endif</td>
                            <td class="whitespace-nowrap">@if ($project->quote) @can('sales.manage') <a class="link font-mono text-xs" href="{{ route('quotes.show', $project->quote) }}">{{ $project->quote->number }}</a> @else <span class="font-mono text-xs">{{ $project->quote->number }}</span> @endcan @else — @endif</td>
                            <td class="text-right whitespace-nowrap">
                                @if ($canManage)
                                    <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $project->id }})">Edit</button>
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $project->id }})" wire:confirm="Delete {{ $project->name }}?">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="$clientId ? 7 : 8">No projects or services here.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($projects->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $projects->links() }}</div> @endif
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit project' : 'New project or service'">
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
                <x-field label="Deadline" name="form.deadline"><input class="input" type="date" wire:model="form.deadline"></x-field>
                <x-field label="Accepted quote" name="form.quote_id" hint="Optional link to the quote this work came from.">
                    <select class="input" wire:model="form.quote_id" @disabled($quotes->isEmpty())><option value="">Not linked</option>@foreach ($quotes as $q) <option value="{{ $q->id }}">{{ $q->number }} · {{ $q->title }}</option> @endforeach</select>
                </x-field>
                <x-field label="Next milestone" name="form.next_milestone"><input class="input" type="text" wire:model="form.next_milestone" placeholder="e.g. Homepage design sign-off"></x-field>
                <x-field label="Milestone date" name="form.next_milestone_date"><input class="input" type="date" wire:model="form.next_milestone_date"></x-field>
                <x-field label="Notes" name="form.notes" class="md:col-span-2"><textarea class="input" rows="2" wire:model="form.notes"></textarea></x-field>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
