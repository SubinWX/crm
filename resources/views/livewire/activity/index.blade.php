<div>
    <x-page-header title="Activity Log" subtitle="Sign-ins and important record changes, with who did it and when." />
    <div class="card">
        <div class="flex flex-wrap gap-3 border-b border-stone-200 p-4">
            <input type="search" class="input w-full md:w-72" placeholder="Search activity…" wire:model.live.debounce.300ms="search" aria-label="Search activity">
            <select class="input w-auto" wire:model.live="type" aria-label="Type">
                <option value="">All activity</option><option value="auth">Sign-ins &amp; accounts</option><option value="records">Record changes</option>
            </select>
            <select class="input w-auto" wire:model.live="user" aria-label="User">
                <option value="">All users</option>
                @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
            </select>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th><th>IP address</th></tr></thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr wire:key="act-{{ $entry->id }}">
                            <td class="whitespace-nowrap">{{ fmt_datetime($entry->created_at) }}</td>
                            <td class="whitespace-nowrap">{{ $entry->user?->name ?? 'System' }}</td>
                            <td><span class="{{ $entry->action === 'login_failed' ? 'badge-red' : 'badge-gray' }}">{{ str($entry->action)->replace('_', ' ') }}</span></td>
                            <td>{{ $entry->description }}</td>
                            <td class="whitespace-nowrap text-stone-500">{{ $entry->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-empty colspan="5">No activity matches.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($entries->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $entries->links() }}</div> @endif
    </div>
</div>
