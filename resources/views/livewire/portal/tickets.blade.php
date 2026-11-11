<div>
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-stone-900">My tickets</h1>
            <p class="mt-0.5 text-sm text-stone-500">{{ $counts['active'] }} open · {{ $counts['all'] }} in total</p>
        </div>
        <a href="{{ route('portal.tickets.create') }}" class="btn-primary"><x-icon name="plus" /> New ticket</a>
    </div>

    <div class="card">
        <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
            <div class="flex gap-1.5" role="group" aria-label="Show">
                @foreach (['active' => 'Open', 'closed' => 'Resolved', 'all' => 'All'] as $key => $label)
                    <button type="button" wire:click="$set('show', '{{ $key }}')" @class(['btn btn-sm border', 'border-ink-900 bg-ink-900 text-white' => $show === $key, 'border-stone-300 bg-white text-stone-700 hover:bg-stone-50' => $show !== $key])>{{ $label }}</button>
                @endforeach
            </div>
            <input type="search" class="input ml-auto w-full sm:w-64" placeholder="Search number or subject…" wire:model.live.debounce.300ms="search" aria-label="Search tickets">
        </div>
        @forelse ($tickets as $ticket)
            <a href="{{ route('portal.tickets.show', $ticket->number) }}" wire:key="t-{{ $ticket->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-stone-100 px-4 py-3.5 last:border-b-0 hover:bg-stone-50">
                <span class="w-32 shrink-0 font-mono text-sm font-semibold text-brand-700">{{ $ticket->number }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium text-stone-900">{{ $ticket->subject }}</span>
                    <span class="block text-xs text-stone-500">{{ \App\Models\SupportTicket::TYPES[$ticket->type] ?? $ticket->type }} · updated {{ $ticket->last_activity_at?->diffForHumans() }}</span>
                </span>
                <x-status :value="['open' => 'sent', 'in_progress' => 'in_progress', 'waiting' => 'pending', 'resolved' => 'paid', 'closed' => 'void'][$ticket->status] ?? 'void'" :label="\App\Models\SupportTicket::PORTAL_STATUSES[$ticket->status] ?? $ticket->status" />
            </a>
        @empty
            <div class="px-4 py-12 text-center">
                <p class="text-sm text-stone-600">{{ $counts['all'] ? 'No tickets match this view.' : 'You have not submitted a ticket yet.' }}</p>
                <a href="{{ route('portal.tickets.create') }}" class="btn-primary mt-4">Submit your first ticket</a>
            </div>
        @endforelse
        @if ($tickets->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $tickets->links() }}</div> @endif
    </div>
</div>
