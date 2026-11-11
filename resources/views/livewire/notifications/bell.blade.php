<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false" wire:poll.60s>
    <button type="button" class="btn-ghost relative" @click="open = !open" :aria-expanded="open"
            aria-label="Notifications{{ $unread ? ', '.$unread.' unread' : '' }}">
        <x-icon name="bell" class="size-5" />
        @if ($unread)
            <span class="absolute -top-0.5 -right-0.5 grid min-w-4.5 place-items-center rounded-full bg-red-600 px-1 text-[10px] leading-4.5 font-bold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>
    <div x-show="open" x-cloak x-transition.opacity class="card absolute right-0 z-30 mt-2 w-96 max-w-[calc(100vw-2rem)] shadow-xl">
        <div class="card-header">
            <h2 class="card-title">Notifications</h2>
            @if ($unread) <button type="button" class="link text-xs" wire:click="markAllRead">Mark all read</button> @endif
        </div>
        <div class="max-h-96 overflow-y-auto">
            @forelse ($recent as $n)
                <div wire:key="bell-{{ $n->id }}" @class(['group flex items-start border-b border-stone-100 last:border-b-0 hover:bg-stone-50', 'bg-brand-50/60' => ! $n->read_at])>
                    <button type="button" wire:click="open('{{ $n->id }}')" class="min-w-0 flex-1 cursor-pointer py-3 pr-1 pl-4 text-left">
                        <span class="flex items-center gap-2">
                            <span class="badge-gray capitalize">{{ $n->data['category'] ?? 'general' }}</span>
                            @if ($n->is_important) <span class="badge-amber"><x-icon name="star" class="size-3 fill-current" /> Important</span> @endif
                            <span class="ml-auto text-xs text-stone-500">{{ $n->created_at->diffForHumans(short: true) }}</span>
                        </span>
                        <span @class(['mt-1 block text-sm text-stone-900', 'font-semibold' => ! $n->read_at])>{{ $n->data['title'] ?? 'Notification' }}</span>
                        <span class="line-clamp-2 block text-xs text-stone-600">{{ $n->data['message'] ?? '' }}</span>
                    </button>
                    {{-- Delete without leaving the page. Important ones ask first. --}}
                    <button type="button" wire:click="delete('{{ $n->id }}')" @if ($n->is_important) wire:confirm="This notification is marked as important. Delete it anyway?" @endif
                            class="mt-2 mr-2 cursor-pointer rounded-md p-1.5 text-stone-400 hover:bg-red-50 hover:text-red-700"
                            aria-label="Delete notification: {{ $n->data['title'] ?? '' }}" title="Delete">
                        <x-icon name="trash" />
                    </button>
                </div>
            @empty
                <x-empty>You're all caught up.</x-empty>
            @endforelse
        </div>
        <a href="{{ route('notifications') }}" class="block border-t border-stone-200 px-4 py-2.5 text-center text-sm font-medium text-brand-700 hover:bg-stone-50">Open Notification Center</a>
    </div>
</div>
