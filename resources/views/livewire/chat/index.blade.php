<div>
    <x-page-header title="Chat" subtitle="Message the whole team in the team room, or one person privately." />

    <div class="card grid h-[calc(100vh-13rem)] min-h-[26rem] overflow-hidden md:grid-cols-[16rem_1fr]">
        {{-- Conversations --}}
        <div class="flex min-h-0 flex-col border-b border-stone-200 md:border-r md:border-b-0">
            <div class="max-h-40 overflow-y-auto p-2 md:max-h-none md:flex-1">
                <button type="button" wire:click="open('')" @class(['flex w-full cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm', 'bg-brand-50 font-semibold text-brand-900' => ! $partner, 'text-stone-700 hover:bg-stone-50' => $partner])>
                    <span class="grid size-8 shrink-0 place-items-center rounded-full bg-brand-600 text-white"><x-icon name="users" /></span>
                    <span class="flex-1 truncate">Team room</span>
                    @if ($teamUnread) <span class="rounded-full bg-red-600 px-1.5 text-[11px] font-bold text-white">{{ $teamUnread }}</span> @endif
                </button>
                <p class="px-2.5 pt-3 pb-1 text-[11px] font-semibold tracking-wide text-stone-500 uppercase">Direct messages</p>
                @foreach ($people as $p)
                    <button type="button" wire:key="person-{{ $p->id }}" wire:click="open('{{ $p->id }}')" @class(['flex w-full cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm', 'bg-brand-50 font-semibold text-brand-900' => $partner?->id === $p->id, 'text-stone-700 hover:bg-stone-50' => $partner?->id !== $p->id])>
                        <x-avatar :src="$p->avatarUrl()" :name="$p->name" size="size-8" />
                        <span class="min-w-0 flex-1"><span class="block truncate">{{ $p->name }}</span><span class="block truncate text-xs font-normal text-stone-500">{{ $p->role_label }}</span></span>
                        @if ($unread[$p->id] ?? 0) <span class="rounded-full bg-red-600 px-1.5 text-[11px] font-bold text-white">{{ $unread[$p->id] }}</span> @endif
                    </button>
                @endforeach
            </div>
        </div>

        {{-- The open conversation --}}
        <div class="flex min-h-0 flex-col">
            <div class="flex items-center gap-2.5 border-b border-stone-200 px-4 py-3">
                @if ($partner)
                    <x-avatar :src="$partner->avatarUrl()" :name="$partner->name" size="size-8" />
                    <div><p class="text-sm font-semibold text-stone-900">{{ $partner->name }}</p><p class="text-xs text-stone-500">Private · only the two of you see this</p></div>
                @else
                    <span class="grid size-8 place-items-center rounded-full bg-brand-600 text-white"><x-icon name="users" /></span>
                    <div><p class="text-sm font-semibold text-stone-900">Team room</p><p class="text-xs text-stone-500">Everyone in the team sees these messages</p></div>
                @endif
            </div>

            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-stone-50/60 px-4 py-4" wire:poll.5s
                 x-data="{ bottom() { this.$nextTick(() => this.$el.scrollTop = this.$el.scrollHeight) } }" x-init="bottom()" x-on:chat-scroll.window="bottom()">
                @forelse ($messages as $m)
                    @php $mine = $m->sender_id === auth()->id(); @endphp
                    <div wire:key="msg-{{ $m->id }}" @class(['flex gap-2.5', 'flex-row-reverse' => $mine])>
                        <x-avatar :src="$m->sender?->avatarUrl()" :name="$m->sender?->name ?? '?'" size="size-7" class="mt-0.5 text-[10px]" />
                        <div @class(['max-w-[75%]', 'text-right' => $mine])>
                            <p class="mb-0.5 text-[11px] text-stone-500">{{ $mine ? 'You' : ($m->sender?->name ?? 'Someone') }} · {{ $m->created_at->timezone(crm_tz())->format($m->created_at->isToday() ? 'g:i A' : 'd M, g:i A') }}</p>
                            <p @class(['inline-block rounded-lg px-3 py-2 text-left text-sm break-words whitespace-pre-wrap', 'bg-brand-600 text-white' => $mine, 'border border-stone-200 bg-white text-stone-800' => ! $mine])>{{ $m->body }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-10 text-center text-sm text-stone-500">No messages yet. Say hello.</p>
                @endforelse
            </div>

            <form wire:submit="send" class="flex items-end gap-2 border-t border-stone-200 p-3">
                <div class="min-w-0 flex-1">
                    <textarea class="input resize-none" rows="1" wire:model="body" placeholder="{{ $partner ? 'Message '.$partner->name : 'Message the team' }}" aria-label="Message"
                              x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }"></textarea>
                    @error('body') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="send">Send</button>
            </form>
        </div>
    </div>
    <p class="mt-2 text-xs text-stone-500">Enter sends, Shift + Enter starts a new line. New messages appear within a few seconds.</p>
</div>
