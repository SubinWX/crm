<div>
    <div class="mb-1 text-sm"><a href="{{ route('clients.index') }}" class="link">← Clients</a></div>
    <div class="mb-3 flex items-center gap-3">
        <x-avatar :src="$client->logoUrl()" :name="$client->name" size="size-14" rounded="rounded-lg" fit="object-contain p-1" />
        <p class="text-xs text-stone-500">{{ $client->logo_path ? 'Brand logo' : 'No brand logo yet' }}@can('client.update', $client) · <a class="link" href="{{ route('clients.edit', $client) }}">{{ $client->logo_path ? 'Change' : 'Add logo' }}</a>@endcan</p>
    </div>
    <x-page-header :title="$client->name">
        @if ($client->isArchived()) <span class="badge-gray">Archived</span>
        @else <x-status :value="$client->status" :label="\App\Models\Client::STATUSES[$client->status] ?? null" /> @endif
        @can('clients.manage')
            <button type="button" class="btn-secondary" wire:click="toggleArchive"
                    wire:confirm="{{ $client->isArchived() ? 'Restore this client?' : 'Archive this client? Records are kept and hosting reminders stop.' }}">
                {{ $client->isArchived() ? 'Restore' : 'Archive' }}
            </button>
        @endcan
        @can('client.update', $client)
            <a href="{{ route('clients.edit', $client) }}" class="btn-secondary">Edit</a>
        @endcan
        @can('sales.manage') <a href="{{ route('quotes.create', ['client' => $client->id]) }}" class="btn-secondary">New Quote</a> @endcan
        @can('finance')
            <a href="{{ route('invoices.create', ['client' => $client->id]) }}" class="btn-secondary">New Invoice</a>
            <a href="{{ route('payments.create', ['client' => $client->id]) }}" class="btn-primary">Record Payment</a>
        @endcan
    </x-page-header>

    <div class="mb-5 flex gap-6 overflow-x-auto border-b border-stone-200" role="tablist">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                    @class(['tab', 'tab-active' => $tab === $key]) wire:click="setTab('{{ $key }}')">{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'overview')
        @if ($financials)
            <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat label="Invoiced (issued)" :value="money($financials['invoiced'])" />
                <x-stat label="Payments applied" :value="money($financials['applied'])" />
                <x-stat label="Pending amount" :value="money($financials['pending'])" hint="Issued invoices − applied payments" :tone="$financials['pending'] > 0 ? 'danger' : 'default'" />
                <x-stat label="Unallocated payments" :value="money($financials['unallocated'])" hint="Received but not applied yet" />
            </div>
        @endif

        <div class="grid gap-5 xl:grid-cols-3">
            <section class="card xl:col-span-2">
                <div class="card-header"><h2 class="card-title">Client details</h2></div>
                <dl class="grid gap-x-6 gap-y-4 p-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    @php
                        $rows = [
                            'Primary contact' => $client->contact_name,
                            'Email' => $client->email,
                            'Phone 1' => $client->phone1,
                            'Phone 2' => $client->phone2,
                            'Website' => $client->website,
                            'Location' => $client->location,
                            'Industry' => $client->industry?->name,
                            'Join date' => $client->join_date ? fmt_date($client->join_date) : null,
                            'Account manager' => $client->accountManager?->name,
                            'Client By' => $client->acquisitionSource?->name,
                            'Referred By' => $client->referrer?->name,
                            'Preferred channel' => \App\Models\Client::CHANNELS[$client->preferred_channel] ?? null,
                            'Tax ID / GSTIN' => $client->tax_id,
                        ];
                    @endphp
                    @foreach ($rows as $label => $value)
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $label }}</dt>
                            <dd class="mt-0.5 break-words text-stone-900">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Work types</dt>
                        <dd class="mt-1 flex flex-wrap gap-1">
                            @forelse ($client->workTypes as $type) <span class="badge-gray">{{ $type->name }}</span> @empty <span class="text-stone-900">—</span> @endforelse
                        </dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Billing address</dt>
                        <dd class="mt-0.5 whitespace-pre-line text-stone-900">{{ $client->billing_address ?: '—' }}</dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Notes</dt>
                        <dd class="mt-0.5 whitespace-pre-line text-stone-900">{{ $client->notes ?: '—' }}</dd>
                    </div>
                </dl>
                <p class="border-t border-stone-100 px-4 py-2.5 text-xs text-stone-500">
                    Added {{ fmt_datetime($client->created_at) }}{{ $client->creator ? ' by '.$client->creator->name : '' }}
                </p>
            </section>

            <div class="space-y-5">
                <section class="card">
                    <div class="card-header">
                        <h2 class="card-title">Hosting</h2>
                        <button type="button" class="link text-xs" wire:click="setTab('renewals')">Manage</button>
                    </div>
                    @forelse ($hosting as $record)
                        <div class="flex items-start justify-between gap-3 border-b border-stone-100 px-4 py-3 last:border-b-0">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-stone-900">{{ $record->domain }}</p>
                                <p class="text-xs text-stone-500">{{ $record->serverType?->name ?? '—' }} · {{ $record->serverProvider?->name ?? 'No provider' }}</p>
                                <p @class(['mt-1 text-xs', 'font-semibold text-red-700' => in_array($record->urgency(), ['overdue', 'week', 'two_months']), 'text-stone-500' => ! in_array($record->urgency(), ['overdue', 'week', 'two_months'])])>
                                    Next payment {{ fmt_date($record->next_payment_date) }} · {{ money($record->renewal_amount) }}
                                </p>
                            </div>
                            <x-urgency :record="$record" />
                        </div>
                    @empty
                        <x-empty>No hosting records yet.</x-empty>
                    @endforelse
                </section>

                @can('workspace')
                <section class="card">
                    <div class="card-header">
                        <h2 class="card-title">Open tasks</h2>
                        <button type="button" class="link text-xs" wire:click="setTab('tasks')">All tasks</button>
                    </div>
                    @forelse ($openTasks as $task)
                        <div class="flex items-center justify-between gap-3 border-b border-stone-100 px-4 py-2.5 text-sm last:border-b-0">
                            <div class="min-w-0">
                                <p class="truncate text-stone-900">{{ $task->title }}</p>
                                <p class="text-xs text-stone-500">{{ $task->assignee?->name ?? 'Unassigned' }}</p>
                            </div>
                            <span @class(['text-xs whitespace-nowrap', 'font-semibold text-red-700' => $task->isOverdue(), 'text-stone-500' => ! $task->isOverdue()])>
                                {{ $task->due_date ? ($task->isOverdue() ? 'Overdue · ' : '').fmt_date($task->due_date) : 'No due date' }}
                            </span>
                        </div>
                    @empty
                        <x-empty>No open tasks.</x-empty>
                    @endforelse
                </section>
                @endcan
            </div>
        </div>
    @elseif ($tab === 'contacts')
        <livewire:clients.contacts :client="$client" :key="'contacts-'.$client->id" />
    @elseif ($tab === 'log')
        <livewire:crm.timeline :client-id="$client->id" :key="'timeline-client-'.$client->id" />
    @elseif ($tab === 'quotes')
        <livewire:quotes.index :client-id="$client->id" :key="'quotes-client-'.$client->id" />
    @elseif ($tab === 'projects')
        <livewire:work.projects :client-id="$client->id" :key="'projects-'.$client->id" />
    @elseif ($tab === 'support')
        <livewire:support.tickets :client-id="$client->id" :key="'support-'.$client->id" />
    @elseif ($tab === 'renewals')
        <div class="space-y-5">
            <livewire:hosting.index :client-id="$client->id" :key="'hosting-'.$client->id" />
            @can('workspace') <livewire:work.renewals :client-id="$client->id" :key="'renewals-'.$client->id" /> @endcan
        </div>
    @elseif ($tab === 'finance')
        <livewire:clients.finance :client="$client" :key="'finance-'.$client->id" />
    @elseif ($tab === 'tasks')
        <livewire:tasks.index :client-id="$client->id" :key="'tasks-'.$client->id" />
    @elseif ($tab === 'files')
        <livewire:clients.files :client="$client" :key="'files-'.$client->id" />
    @elseif ($tab === 'activity')
        <section class="card">
            <div class="card-header"><h2 class="card-title">Activity</h2><span class="text-xs text-stone-500">Latest 100 entries</span></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>When</th><th>Who</th><th>What</th></tr></thead>
                    <tbody>
                        @forelse ($activity as $entry)
                            <tr>
                                <td class="whitespace-nowrap">{{ fmt_datetime($entry->created_at) }}</td>
                                <td class="whitespace-nowrap">{{ $entry->user?->name ?? 'System' }}</td>
                                <td>{{ $entry->description }}</td>
                            </tr>
                        @empty
                            <x-empty colspan="3">No activity recorded yet.</x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
