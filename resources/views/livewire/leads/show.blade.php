<div>
    <div class="mb-1 text-sm"><a href="{{ route('leads.index') }}" class="link">← Leads</a></div>
    <x-page-header :title="$lead->name" :subtitle="'Lead · added '.fmt_date($lead->created_at->timezone(crm_tz()))">
        <x-status :value="['new' => 'sent', 'contacted' => 'in_progress', 'proposal_sent' => 'pending', 'won' => 'paid', 'lost' => 'void'][$lead->stage] ?? 'void'" :label="\App\Models\Lead::STAGES[$lead->stage] ?? $lead->stage" />
        <a href="{{ route('leads.index', ['search' => $lead->name, 'stage' => 'all']) }}" class="btn-secondary">Edit in list</a>
        <a href="{{ route('quotes.create', ['lead' => $lead->id]) }}" class="btn-secondary"><x-icon name="plus" /> New Quote</a>
        @if ($lead->isConverted())
            @can('clients.view') <a href="{{ route('clients.show', $lead->client) }}" class="btn-primary">Open client</a> @endcan
        @else
            <button type="button" class="btn-primary" wire:click="convert"
                    wire:confirm="{{ $match ? 'A client named '.$match->name.' already matches this lead. Link the lead to that client?' : 'Convert '.$lead->name.' to a client? Contacts, notes, follow-ups and quotes move across.' }}">Convert to client</button>
        @endif
    </x-page-header>

    @if ($lead->isConverted())
        <div class="card mb-5 border-brand-200 bg-brand-50 p-4 text-sm text-brand-800">Converted to the client <strong>{{ $lead->client?->name }}</strong> on {{ fmt_date($lead->converted_at?->timezone(crm_tz())) }}. This page is kept as the record of the sale.</div>
    @elseif ($match)
        <div class="card mb-5 border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">Possible duplicate: the client <strong>{{ $match->name }}</strong> has the same {{ $lead->email ? 'email' : 'name' }}. Converting will link this lead to that client instead of creating a new one.</div>
    @endif

    <div class="grid gap-5 xl:grid-cols-3">
        <div class="space-y-5">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Details</h2></div>
                <dl class="grid gap-4 p-4 text-sm sm:grid-cols-2 xl:grid-cols-1">
                    @foreach ([
                        'Contact' => $lead->contact_name, 'Email' => $lead->email, 'Phone' => $lead->phone, 'Website' => $lead->website, 'Location' => $lead->location,
                        'Acquisition source' => $lead->source?->name, 'Estimated value' => $lead->estimated_value !== null ? money($lead->estimated_value) : null, 'Owner' => $lead->owner?->name,
                    ] as $label => $value)
                        <div><dt class="label">{{ $label }}</dt><dd class="break-words text-stone-900">{{ $value ?: '—' }}</dd></div>
                    @endforeach
                    <div class="sm:col-span-2 xl:col-span-1"><dt class="label">Services requested</dt><dd class="mt-1 flex flex-wrap gap-1">@forelse ($lead->services as $service) <span class="badge-gray">{{ $service->name }}</span> @empty — @endforelse</dd></div>
                    <div class="sm:col-span-2 xl:col-span-1"><dt class="label">Next action</dt><dd @class(['font-semibold text-red-700' => $lead->next_action_date && days_until($lead->next_action_date) <= 0])>{{ $lead->next_action ? $lead->next_action.' · '.fmt_date($lead->next_action_date) : '—' }}</dd></div>
                    @if ($lead->lost_reason) <div class="sm:col-span-2 xl:col-span-1"><dt class="label">Reason lost</dt><dd>{{ $lead->lost_reason }}</dd></div> @endif
                    <div class="sm:col-span-2 xl:col-span-1"><dt class="label">Notes</dt><dd class="rich text-stone-900">{{ $lead->notes ? rich($lead->notes) : '—' }}</dd></div>
                </dl>
            </section>

            @unless ($lead->isConverted())
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Move stage</h2></div>
                    <div class="flex flex-wrap gap-2 p-4">
                        @foreach (\App\Models\Lead::STAGES as $key => $label)
                            @if ($key !== 'lost')
                                <button type="button" wire:click="setStage('{{ $key }}')" @class(['btn btn-sm border', 'border-ink-900 bg-ink-900 text-white' => $lead->stage === $key, 'border-stone-300 bg-white text-stone-700 hover:bg-stone-50' => $lead->stage !== $key])>{{ $label }}</button>
                            @endif
                        @endforeach
                    </div>
                    <div class="flex items-end gap-2 border-t border-stone-200 p-4">
                        <x-field label="Reason (if lost)" class="flex-1"><input class="input" type="text" wire:model="lostReason" placeholder="e.g. Budget, went elsewhere"></x-field>
                        <button type="button" class="btn-danger mb-0.5" wire:click="setStage('lost')">Mark lost</button>
                    </div>
                </section>
            @endunless
        </div>

        <div class="space-y-5 xl:col-span-2">
            <livewire:tasks.index :lead-id="$lead->id" :key="'tasks-lead-'.$lead->id" />
            <livewire:crm.timeline :lead-id="$lead->id" :key="'timeline-lead-'.$lead->id" />
            <livewire:quotes.index :lead-id="$lead->id" :key="'quotes-lead-'.$lead->id" />
        </div>
    </div>
</div>
