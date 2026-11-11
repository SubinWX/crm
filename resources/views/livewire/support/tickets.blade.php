<div>
    @unless ($embedded)
        <x-page-header title="Support Tickets" subtitle="Requests from the support portal, and requests the team logs for a customer.">
            <a href="{{ route('portal.home') }}" target="_blank" rel="noopener" class="btn-secondary">Open portal ↗</a>
            @can('portal-users.manage') <a href="{{ route('support.portal-users') }}" class="btn-secondary">Portal users</a> @endcan
            <button type="button" class="btn-primary" wire:click="create"><x-icon name="plus" /> New ticket</button>
        </x-page-header>

        <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat label="New / open" :value="$counts['open']" hint="Waiting for the team" :tone="$counts['open'] ? 'danger' : 'default'" />
            <x-stat label="In progress" :value="$counts['in_progress']" />
            <x-stat label="Waiting for customer" :value="$counts['waiting']" />
            <x-stat label="Unassigned" :value="$counts['unassigned']" hint="Active tickets with no owner" />
        </div>
    @endunless

    <div class="card">
        @if ($embedded)
            <div class="card-header"><h2 class="card-title">Support tickets for this client</h2><button type="button" class="btn-primary btn-sm" wire:click="create"><x-icon name="plus" /> New ticket</button></div>
        @endif
        <div class="grid gap-3 border-b border-stone-200 p-4 md:grid-cols-3 xl:grid-cols-6">
            <div class="relative md:col-span-3 xl:col-span-2">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
                <input type="search" class="input pl-9" placeholder="Search number, subject, customer, company…" wire:model.live.debounce.300ms="search" aria-label="Search tickets">
            </div>
            <select class="input" wire:model.live="status" aria-label="Status">
                <option value="active">All active</option>
                @foreach (\App\Models\SupportTicket::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                <option value="all">Everything</option>
            </select>
            <select class="input" wire:model.live="type" aria-label="Type">
                <option value="">All types</option>
                @foreach (\App\Models\SupportTicket::TYPES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
            <select class="input" wire:model.live="priority" aria-label="Priority">
                <option value="">Any priority</option>
                @foreach (\App\Models\SupportTicket::PRIORITIES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
            <select class="input" wire:model.live="assignee" aria-label="Assignee">
                <option value="">Anyone</option><option value="me">Assigned to me</option><option value="unassigned">Unassigned</option>
                @foreach ($team as $member) <option value="{{ $member->id }}">{{ $member->name }}</option> @endforeach
            </select>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Ticket</th><th>Subject</th><th>From</th><th>Type</th><th>Priority</th><th>Status</th><th>Assigned to</th><th>Last activity</th></tr></thead>
                <tbody>
                    @forelse ($tickets as $ticket)
                        <tr wire:key="ticket-{{ $ticket->id }}">
                            <td class="whitespace-nowrap"><a class="link font-mono" href="{{ route('support.tickets.show', $ticket) }}">{{ $ticket->number }}</a></td>
                            <td class="max-w-80">
                                <a href="{{ route('support.tickets.show', $ticket) }}" class="font-medium text-stone-900 hover:underline">{{ $ticket->subject }}</a>
                                @if ($ticket->related_work) <p class="truncate text-xs text-stone-500">{{ $ticket->related_work }}</p> @endif
                            </td>
                            <td>
                                <p>{{ $ticket->portalUser?->name ?? '—' }}</p>
                                <p class="text-xs text-stone-500">{{ $ticket->client?->name ?? $ticket->portalUser?->company ?? $ticket->portalUser?->email }}</p>
                            </td>
                            <td class="whitespace-nowrap">{{ \App\Models\SupportTicket::TYPES[$ticket->type] ?? $ticket->type }}@if ($ticket->rating) <span class="text-xs text-stone-500">· {{ $ticket->rating }}/5</span>@endif</td>
                            <td>
                                @if ($ticket->priority === 'urgent') <span class="badge-red-solid">Urgent</span>
                                @elseif ($ticket->priority === 'high') <span class="badge-red">High</span>
                                @else <span class="badge-gray">{{ \App\Models\SupportTicket::PRIORITIES[$ticket->priority] ?? $ticket->priority }}</span> @endif
                            </td>
                            <td><x-status :value="['open' => 'sent', 'in_progress' => 'in_progress', 'waiting' => 'pending', 'resolved' => 'paid', 'closed' => 'void'][$ticket->status] ?? 'void'" :label="\App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status" /></td>
                            <td>{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                            <td class="whitespace-nowrap text-stone-600">{{ $ticket->last_activity_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <x-empty colspan="8">No tickets match these filters.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($tickets->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $tickets->links() }}</div> @endif
    </div>

    @if ($showForm)
        <x-modal title="New ticket for a customer">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <x-field label="Customer (portal user)" name="form.portal_user_id" required class="md:col-span-2" hint="{{ $customers->isEmpty() ? 'No portal users yet. Add one under Support → Portal Users first.' : 'They are emailed the ticket number and can follow it on the portal.' }}">
                    <select class="input" wire:model="form.portal_user_id">
                        <option value="">Select customer…</option>
                        @foreach ($customers as $customer) <option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->company ?: $customer->email }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Type" name="form.type"><select class="input" wire:model="form.type">@foreach (\App\Models\SupportTicket::TYPES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select></x-field>
                <x-field label="Priority" name="form.priority"><select class="input" wire:model="form.priority">@foreach (\App\Models\SupportTicket::PRIORITIES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select></x-field>
                <x-field label="Subject" name="form.subject" required class="md:col-span-2"><input class="input" type="text" wire:model="form.subject" placeholder="e.g. Bug: checkout button not working on mobile"></x-field>
                <x-field label="Project or service" name="form.related_work" class="md:col-span-2"><input class="input" type="text" wire:model="form.related_work"></x-field>
                <x-field label="Details" name="form.description" required class="md:col-span-2"><textarea class="input" rows="4" wire:model="form.description" placeholder="What the customer asked for, in their words."></textarea></x-field>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Create ticket</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
