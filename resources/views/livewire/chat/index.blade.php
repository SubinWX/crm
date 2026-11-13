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
                            @if ($m->attachment_path)
                                @if ($m->isImage())
                                    <a href="{{ route('chat.attachment', $m) }}" target="_blank" rel="noopener" class="mb-1 block" title="Open the picture">
                                        <img src="{{ route('chat.attachment', $m) }}" alt="{{ $m->attachment_name }}" loading="lazy" class="inline-block max-h-64 max-w-full rounded-lg border border-stone-200 bg-white object-contain">
                                    </a>
                                @else
                                    <a href="{{ route('chat.attachment', ['message' => $m, 'download' => 1]) }}" class="mb-1 inline-flex max-w-full items-center gap-2 rounded-lg border border-stone-200 bg-white px-3 py-2 text-left text-sm text-stone-800 hover:bg-stone-50">
                                        <x-icon name="document" class="size-5 text-stone-400" />
                                        <span class="min-w-0"><span class="block truncate font-medium">{{ $m->attachment_name }}</span><span class="block text-xs text-stone-500">{{ $m->attachmentSize() }} · Download</span></span>
                                    </a>
                                @endif
                                @if (filled($m->body)) <br> @endif
                            @endif
                            @if (filled($m->body))
                                <p @class(['inline-block rounded-lg px-3 py-2 text-left text-sm break-words whitespace-pre-wrap', 'bg-brand-600 text-white' => $mine, 'border border-stone-200 bg-white text-stone-800' => ! $mine])>{{ $m->body }}</p>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="py-10 text-center text-sm text-stone-500">No messages yet. Say hello.</p>
                @endforelse
            </div>

            {{-- Pictures and files can be chosen with the clip button, dropped on the box, or pasted (Ctrl/Cmd + V). --}}
            <form wire:submit="send" class="border-t border-stone-200 p-3"
                  x-data="{ over: false, attach(files) { if (files && files.length) { $wire.upload('file', files[0]) } } }"
                  x-on:dragover.prevent="over = true" x-on:dragleave.prevent="over = false" x-on:drop.prevent="over = false; attach($event.dataTransfer.files)"
                  :class="over && 'bg-brand-50'">
                @if ($file && ! $errors->has('file'))
                    <div class="mb-2 flex items-center gap-3 rounded-md border border-stone-200 bg-stone-50 px-3 py-2">
                        @if (str_starts_with((string) $file->getMimeType(), 'image/'))
                            <img src="{{ $file->temporaryUrl() }}" alt="Picture to send" class="size-12 rounded border border-stone-200 object-cover">
                        @else
                            <x-icon name="document" class="size-6 text-stone-400" />
                        @endif
                        <span class="min-w-0 flex-1 truncate text-sm text-stone-700">{{ $file->getClientOriginalName() }} <span class="text-xs text-stone-500">· ready to send</span></span>
                        <button type="button" class="btn-ghost btn-sm" wire:click="removeFile" aria-label="Remove the attachment"><x-icon name="x" /></button>
                    </div>
                @endif
                <p wire:loading wire:target="file" class="mb-2 text-xs text-stone-500">Attaching…</p>
                @error('file') <p class="field-error mb-2">{{ $message }}</p> @enderror
                <div class="flex items-end gap-2">
                    <label class="btn-secondary cursor-pointer" title="Attach a picture or file">
                        <x-icon name="photo" /> <span class="sr-only">Attach a picture or file</span>
                        <input type="file" class="hidden" wire:model="file" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.zip">
                    </label>
                    <div class="min-w-0 flex-1">
                        <textarea class="input resize-none" rows="1" wire:model="body" placeholder="{{ $partner ? 'Message '.$partner->name : 'Message the team' }}" aria-label="Message"
                                  x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send() }"
                                  x-on:paste="if ($event.clipboardData && $event.clipboardData.files.length) { $event.preventDefault(); attach($event.clipboardData.files) }"></textarea>
                        @error('body') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="send,file">Send</button>
                </div>
            </form>
        </div>
    </div>
    <p class="mt-2 text-xs text-stone-500">Enter sends, Shift + Enter starts a new line. Attach a picture or file with the picture button, by dropping it on the box, or by pasting a copied image. Up to 10 MB.</p>
</div>
