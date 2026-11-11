<div>
    <x-page-header title="Notification Center" :subtitle="($totalUnread ? $totalUnread.' unread' : 'Everything has been read').($importantCount ? ' · '.$importantCount.' important' : '')">
        <label class="flex items-center gap-2 text-sm text-stone-600"><input type="checkbox" class="rounded border-stone-300" wire:model.live="unreadOnly"> Unread only</label>
        <label class="flex items-center gap-2 text-sm text-stone-600"><input type="checkbox" class="rounded border-stone-300" wire:model.live="importantOnly"> Important only</label>
        <button type="button" class="btn-secondary" wire:click="markAllRead" @disabled(! $totalUnread)>Mark {{ $category ? 'these' : 'all' }} as read</button>
        <button type="button" class="btn-danger" wire:click="deleteRead" @disabled(! $readCount)
                wire:confirm="Delete {{ $readCount }} read {{ str('notification')->plural($readCount) }}{{ $category ? ' in this group' : '' }}? Unread and important ones are kept. This cannot be undone.">Delete read ({{ $readCount }})</button>
    </x-page-header>

    <div class="mb-5 flex gap-6 overflow-x-auto border-b border-stone-200" role="tablist">
        <button type="button" role="tab" @class(['tab', 'tab-active' => $category === '']) wire:click="setCategory('')">All</button>
        @foreach (\App\Livewire\Notifications\Center::CATEGORIES as $key => $label)
            <button type="button" role="tab" @class(['tab flex items-center gap-1.5', 'tab-active' => $category === $key]) wire:click="setCategory('{{ $key }}')">
                {{ $label }} @if ($counts[$key]) <span class="rounded-full bg-red-600 px-1.5 text-[11px] font-bold text-white">{{ $counts[$key] }}</span> @endif
            </button>
        @endforeach
    </div>

    @forelse (collect(['support', 'client', 'hosting', 'renewal', 'invoice', 'other'])->filter(fn ($k) => $groups->has($k)) as $key)
        <section class="card mb-5" wire:key="group-{{ $key }}">
            <div class="card-header"><h2 class="card-title">{{ \App\Livewire\Notifications\Center::CATEGORIES[$key] ?? 'Other' }}</h2><span class="text-xs text-stone-500">{{ $groups[$key]->count() }} on this page</span></div>
            @foreach ($groups[$key] as $n)
                <div wire:key="n-{{ $n->id }}" @class(['flex items-start gap-3 border-b border-stone-100 px-4 py-3 last:border-b-0', 'bg-amber-50/70' => $n->is_important, 'bg-brand-50/50' => ! $n->read_at && ! $n->is_important])>
                    <button type="button" wire:click="toggleImportant('{{ $n->id }}')" aria-pressed="{{ $n->is_important ? 'true' : 'false' }}"
                            class="mt-0.5 cursor-pointer rounded p-0.5 {{ $n->is_important ? 'text-amber-500' : 'text-stone-300 hover:text-amber-500' }}"
                            aria-label="{{ $n->is_important ? 'Remove important mark' : 'Mark as important' }}" title="{{ $n->is_important ? 'Important — click to remove' : 'Mark as important' }}">
                        <x-icon name="star" class="size-5 {{ $n->is_important ? 'fill-current' : '' }}" />
                    </button>
                    <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-brand-600' => ! $n->read_at, 'bg-transparent' => $n->read_at]) aria-hidden="true"></span>
                    <div class="min-w-0 flex-1">
                        <button type="button" wire:click="open('{{ $n->id }}')" @class(['cursor-pointer text-left text-sm text-stone-900 hover:underline', 'font-semibold' => ! $n->read_at])>{{ $n->data['title'] ?? 'Notification' }}</button>
                        <p class="text-sm text-stone-600">{{ $n->data['message'] ?? '' }}</p>
                        <p class="mt-0.5 text-xs text-stone-500">{{ fmt_datetime($n->created_at) }}@if ($n->is_important) · <span class="font-semibold text-amber-700">Important</span>@endif</p>
                    </div>
                    <button type="button" class="btn-ghost btn-sm" wire:click="toggleRead('{{ $n->id }}')">{{ $n->read_at ? 'Mark unread' : 'Mark read' }}</button>
                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete('{{ $n->id }}')" wire:confirm="Delete this notification? This cannot be undone." aria-label="Delete notification"><x-icon name="trash" /> Delete</button>
                </div>
            @endforeach
        </section>
    @empty
        <div class="card"><x-empty>{{ $importantOnly ? 'Nothing is marked as important.' : 'No notifications'.($unreadOnly ? ' left unread' : '').'.' }}</x-empty></div>
    @endforelse

    @if ($notifications->hasPages()) <div class="mt-4">{{ $notifications->links() }}</div> @endif
</div>
