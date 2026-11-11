<div>
    <x-page-header title="Clients" :subtitle="$clients->total().' '.str('client')->plural($clients->total()).($archived ? ' in the archive' : '')">
        @can('clients.manage')
            <a href="{{ route('clients.export', array_filter($this->filters())) }}" class="btn-secondary"><x-icon name="download" /> Export CSV</a>
            <a href="{{ route('import', ['type' => 'clients']) }}" class="btn-secondary">Import CSV</a>
        @endcan
        @can('clients.create')
            <a href="{{ route('clients.create') }}" class="btn-primary"><x-icon name="plus" /> New Client</a>
        @endcan
    </x-page-header>

    <div class="card">
        <div class="grid gap-3 border-b border-stone-200 p-4 md:grid-cols-3 xl:grid-cols-6">
            <div class="relative md:col-span-3 xl:col-span-2">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
                <input type="search" class="input pl-9" placeholder="Search name, contact, email, phone, website…" wire:model.live.debounce.300ms="search" aria-label="Search clients">
            </div>
            <select class="input" wire:model.live="status" aria-label="Status">
                <option value="">All statuses</option>
                @foreach (\App\Models\Client::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
            <select class="input" wire:model.live="industry" aria-label="Industry">
                <option value="">All industries</option>
                @foreach ($industries as $o) <option value="{{ $o->id }}">{{ $o->name }}</option> @endforeach
            </select>
            <select class="input" wire:model.live="workType" aria-label="Work type">
                <option value="">All work types</option>
                @foreach ($workTypes as $o) <option value="{{ $o->id }}">{{ $o->name }}</option> @endforeach
            </select>
            <select class="input" wire:model.live="source" aria-label="Acquisition source">
                <option value="">All sources (Client By)</option>
                @foreach ($sources as $o) <option value="{{ $o->id }}">{{ $o->name }}</option> @endforeach
            </select>
            <select class="input" wire:model.live="manager" aria-label="Account manager">
                <option value="">All account managers</option>
                @foreach ($managers as $o) <option value="{{ $o->id }}">{{ $o->name }}{{ $o->is_active ? '' : ' (inactive)' }}</option> @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" class="rounded border-stone-300" wire:model.live="archived"> Show archived
            </label>
            @if ($search || $status || $industry || $workType || $source || $manager || $archived)
                <button type="button" class="link justify-self-start text-sm" wire:click="clearFilters">Clear filters</button>
            @endif
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Client</th><th>Contact</th><th>Industry</th><th>Work types</th><th>Client By</th>
                        <th>Account manager</th><th>Status</th><th>Joined</th><th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clients as $client)
                        <tr wire:key="client-{{ $client->id }}">
                            <td>
                                <div class="flex items-center gap-3">
                                    <x-avatar :src="$client->logoUrl()" :name="$client->name" rounded="rounded-md" fit="object-contain" />
                                    <div class="min-w-0">
                                        <a href="{{ route('clients.show', $client) }}" class="link text-stone-900">{{ $client->name }}</a>
                                        @if ($client->location) <div class="text-xs text-stone-500">{{ $client->location }}</div> @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div>{{ $client->contact_name ?: '—' }}</div>
                                <div class="text-xs text-stone-500">{{ $client->email ?: $client->phone1 }}</div>
                            </td>
                            <td>{{ $client->industry?->name ?? '—' }}</td>
                            <td>
                                <div class="flex max-w-56 flex-wrap gap-1">
                                    @forelse ($client->workTypes as $type) <span class="badge-gray">{{ $type->name }}</span> @empty — @endforelse
                                </div>
                            </td>
                            <td>{{ $client->acquisitionSource?->name ?? '—' }}</td>
                            <td>{{ $client->accountManager?->name ?? '—' }}</td>
                            <td>
                                @if ($client->isArchived()) <span class="badge-gray">Archived</span>
                                @else <x-status :value="$client->status" :label="\App\Models\Client::STATUSES[$client->status] ?? null" /> @endif
                            </td>
                            <td class="whitespace-nowrap">{{ fmt_date($client->join_date) }}</td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('clients.show', $client) }}" class="btn-ghost btn-sm">View</a>
                                @can('client.update', $client)
                                    <a href="{{ route('clients.edit', $client) }}" class="btn-ghost btn-sm">Edit</a>
                                @endcan
                                @can('clients.manage')
                                    @if ($client->isArchived())
                                        <button type="button" class="btn-ghost btn-sm" wire:click="restore({{ $client->id }})">Restore</button>
                                    @else
                                        <button type="button" class="btn-ghost btn-sm" wire:click="archive({{ $client->id }})"
                                                wire:confirm="Archive {{ $client->name }}? Their records are kept and reminders stop.">Archive</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="9">No clients match these filters.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($clients->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $clients->links() }}</div> @endif
    </div>
</div>
