<div class="mx-auto max-w-3xl" wire:poll.30s>
    <div class="mb-1 text-sm"><a href="{{ route('portal.tickets.index') }}" class="link">← My tickets</a></div>
    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="font-mono text-sm font-semibold text-brand-700">{{ $ticket->number }}</p>
            <h1 class="text-2xl font-semibold text-stone-900">{{ $ticket->subject }}</h1>
            <p class="mt-1 text-sm text-stone-500">
                {{ \App\Models\SupportTicket::TYPES[$ticket->type] ?? $ticket->type }}
                @if ($ticket->related_work) · {{ $ticket->related_work }} @endif
                @if ($ticket->type !== 'feedback') · {{ \App\Models\SupportTicket::PRIORITIES[$ticket->priority] }} priority @endif
                @if ($ticket->rating) · Rated {{ $ticket->rating }}/5 @endif
                · opened {{ fmt_datetime($ticket->created_at) }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-status :value="['open' => 'sent', 'in_progress' => 'in_progress', 'waiting' => 'pending', 'resolved' => 'paid', 'closed' => 'void'][$ticket->status] ?? 'void'" :label="\App\Models\SupportTicket::PORTAL_STATUSES[$ticket->status] ?? $ticket->status" />
            @if ($ticket->isActive())
                <button type="button" class="btn-secondary btn-sm" wire:click="markResolved" wire:confirm="Mark this ticket as resolved?">Mark as resolved</button>
            @endif
        </div>
    </div>

    <div class="space-y-4">
        @foreach ($messages as $message)
            <article wire:key="m-{{ $message->id }}" @class(['card p-4 sm:p-5', 'border-brand-200 bg-brand-50/40' => $message->fromTeam()])>
                <header class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="font-semibold text-stone-900">{{ $message->fromTeam() ? $message->authorName().' · '.setting('company_name', config('app.name')) : 'You' }}</span>
                    <time class="text-xs text-stone-500">{{ fmt_datetime($message->created_at) }}</time>
                </header>
                <div class="text-sm leading-relaxed whitespace-pre-line text-stone-800">{{ $message->body }}</div>
                @if ($message->attachments->isNotEmpty())
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach ($message->attachments as $attachment)
                            <li><a href="{{ route('portal.attachments', $attachment) }}" class="btn-secondary btn-sm"><x-icon name="download" /> {{ $attachment->name }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </article>
        @endforeach
    </div>

    @if ($ticket->status === 'closed')
        <p class="card mt-5 p-4 text-sm text-stone-600">This ticket is closed. <a class="link" href="{{ route('portal.tickets.create') }}">Open a new ticket</a> if you need more help.</p>
    @else
        <form wire:submit="send" class="card mt-5 space-y-3 p-4 sm:p-5">
            <x-field :label="$ticket->status === 'resolved' ? 'Reply to reopen this ticket' : 'Add a reply'" name="reply">
                <textarea class="input" rows="4" wire:model="reply" maxlength="10000" placeholder="Write your message…"></textarea>
            </x-field>
            <div>
                <input type="file" class="input" multiple wire:model="files" aria-label="Attachments">
                <p wire:loading wire:target="files" class="mt-1 text-xs text-stone-500">Uploading…</p>
                @error('files') <p class="field-error">{{ $message }}</p> @enderror
                @error('files.*') <p class="field-error">{{ $message }}</p> @enderror
                @foreach ($files as $index => $file)
                    <p class="mt-1 flex items-center justify-between gap-2 rounded-md bg-stone-50 px-3 py-1.5 text-sm"><span class="truncate">{{ $file->getClientOriginalName() }}</span><button type="button" class="link text-xs text-red-700" wire:click="removeFile({{ $index }})">Remove</button></p>
                @endforeach
            </div>
            <div class="flex justify-end"><button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="files,send">Send reply</button></div>
        </form>
    @endif
</div>
