<div>
    <x-page-header title="Leads" subtitle="Enquiries on their way to becoming clients.">
        <a href="{{ route('leads.export', array_filter($this->filters())) }}" class="btn-secondary"><x-icon name="download" /> Export CSV</a>
        <a href="{{ route('import', ['type' => 'leads']) }}" class="btn-secondary">Import CSV</a>
        <button type="button" class="btn-primary" wire:click="create"><x-icon name="plus" /> New Lead</button>
    </x-page-header>

    <div class="mb-5 grid gap-3 sm:grid-cols-3 xl:grid-cols-5">
        @foreach (\App\Models\Lead::STAGES as $key => $label)
            <button type="button" wire:click="$set('stage', '{{ $key }}')" @class(['card cursor-pointer p-3 text-left hover:border-stone-300', 'border-brand-500 ring-1 ring-brand-500' => $stage === $key])>
                <span class="text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $label }}</span>
                <span class="mt-0.5 block text-xl font-semibold tabular-nums text-stone-900">{{ $counts[$key]->total ?? 0 }}</span>
                <span class="block text-xs tabular-nums text-stone-500">{{ money($counts[$key]->value ?? 0) }}</span>
            </button>
        @endforeach
    </div>

    <div class="card">
        <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
            <div class="relative w-full md:w-72">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
                <input type="search" class="input pl-9" placeholder="Search name, contact, email, phone…" wire:model.live.debounce.300ms="search" aria-label="Search leads">
            </div>
            <select class="input w-auto" wire:model.live="stage" aria-label="Stage">
                <option value="open">Open leads</option>
                @foreach (\App\Models\Lead::STAGES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                <option value="all">All leads</option>
            </select>
            <select class="input w-auto" wire:model.live="owner" aria-label="Owner">
                <option value="">Any owner</option>
                @foreach ($owners as $o) <option value="{{ $o->id }}">{{ $o->name }}</option> @endforeach
            </select>
            <select class="input w-auto" wire:model.live="source" aria-label="Source">
                <option value="">Any source</option>
                @foreach ($sources as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach
            </select>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Lead</th><th>Contact</th><th>Services requested</th><th>Source</th><th class="num">Est. value</th><th>Owner</th><th>Stage</th><th>Next action</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($leads as $lead)
                        <tr wire:key="lead-{{ $lead->id }}">
                            <td><a class="link text-stone-900" href="{{ route('leads.show', $lead) }}">{{ $lead->name }}</a>@if ($lead->location)<div class="text-xs text-stone-500">{{ $lead->location }}</div>@endif</td>
                            <td>{{ $lead->contact_name ?: '—' }}<div class="text-xs text-stone-500">{{ $lead->email ?: $lead->phone }}</div></td>
                            <td><div class="flex max-w-56 flex-wrap gap-1">@forelse ($lead->services as $service) <span class="badge-gray">{{ $service->name }}</span> @empty — @endforelse</div></td>
                            <td>{{ $lead->source?->name ?? '—' }}</td>
                            <td class="num">{{ $lead->estimated_value !== null ? money($lead->estimated_value) : '—' }}</td>
                            <td>{{ $lead->owner?->name ?? 'Unassigned' }}</td>
                            <td>
                                <x-status :value="['new' => 'sent', 'contacted' => 'in_progress', 'proposal_sent' => 'pending', 'won' => 'paid', 'lost' => 'void'][$lead->stage] ?? 'void'" :label="\App\Models\Lead::STAGES[$lead->stage] ?? $lead->stage" />
                                @if ($lead->isConverted()) <div class="mt-0.5 text-xs text-stone-500">Now a client</div> @endif
                            </td>
                            <td class="max-w-56">
                                @if ($lead->next_action_date)
                                    <span @class(['text-sm', 'font-semibold text-red-700' => days_until($lead->next_action_date) <= 0])>{{ fmt_date($lead->next_action_date) }}{{ days_until($lead->next_action_date) < 0 ? ' · overdue' : (days_until($lead->next_action_date) === 0 ? ' · today' : '') }}</span>
                                    <p class="truncate text-xs text-stone-500">{{ $lead->next_action }}</p>
                                @else — @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('leads.show', $lead) }}" class="btn-ghost btn-sm">Open</a>
                                <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $lead->id }})">Edit</button>
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="9">No leads match these filters.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($leads->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $leads->links() }}</div> @endif
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit lead' : 'New lead'" size="max-w-3xl">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <x-field label="Company / lead name" name="form.name" required><input class="input" type="text" wire:model="form.name"></x-field>
                <x-field label="Contact name" name="form.contact_name"><input class="input" type="text" wire:model="form.contact_name"></x-field>
                <x-field label="Email" name="form.email"><input class="input" type="email" wire:model="form.email"></x-field>
                <x-field label="Phone" name="form.phone"><input class="input" type="tel" wire:model="form.phone"></x-field>
                <x-field label="Website" name="form.website"><input class="input" type="text" wire:model="form.website"></x-field>
                <x-field label="Location" name="form.location"><input class="input" type="text" wire:model="form.location"></x-field>
                <x-field label="Acquisition source" name="form.acquisition_source_id">
                    <select class="input" wire:model="form.acquisition_source_id"><option value="">Select…</option>@foreach ($sources as $s) <option value="{{ $s->id }}">{{ $s->name }}</option> @endforeach</select>
                </x-field>
                <x-field label="Estimated value ({{ setting('currency', 'INR') }})" name="form.estimated_value"><input class="input" type="number" step="0.01" min="0" wire:model="form.estimated_value"></x-field>
                <x-field label="Owner" name="form.owner_id">
                    <select class="input" wire:model="form.owner_id"><option value="">Unassigned</option>@foreach ($owners as $o) <option value="{{ $o->id }}">{{ $o->name }}</option> @endforeach</select>
                </x-field>
                <x-field label="Services requested" name="serviceIds" class="md:col-span-2 lg:col-span-3">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($services as $service)
                            <label class="flex cursor-pointer items-center gap-2 rounded-md border border-stone-300 bg-white px-3 py-1.5 text-sm has-checked:border-brand-500 has-checked:bg-brand-50"><input type="checkbox" class="rounded border-stone-300" value="{{ $service->id }}" wire:model="serviceIds"> {{ $service->name }}</label>
                        @endforeach
                    </div>
                </x-field>
                <x-field label="Stage" name="form.stage" required>
                    <select class="input" wire:model.live="form.stage">@foreach (\App\Models\Lead::STAGES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select>
                </x-field>
                <x-field label="Next action" name="form.next_action"><input class="input" type="text" wire:model="form.next_action" placeholder="e.g. Call to discuss scope"></x-field>
                <x-field label="Next action due" name="form.next_action_date"><input class="input" type="date" wire:model="form.next_action_date"></x-field>
                @if ($form['stage'] === 'lost')
                    <x-field label="Reason lost" name="form.lost_reason" class="md:col-span-2 lg:col-span-3"><input class="input" type="text" wire:model="form.lost_reason"></x-field>
                @endif
                <x-field label="Notes" name="form.notes" class="md:col-span-2 lg:col-span-3"><x-editor model="form.notes" :value="$form['notes']" min-height="5rem" label="Notes" /></x-field>
                <div class="flex justify-end gap-2 md:col-span-2 lg:col-span-3">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Save lead</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
