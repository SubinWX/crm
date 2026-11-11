<div wire:poll.30s>
    <div class="mb-1 text-sm"><a href="{{ route('support.tickets.index') }}" class="link">← Support Tickets</a></div>
    <x-page-header :title="$ticket->subject" :subtitle="$ticket->number.' · '.(\App\Models\SupportTicket::TYPES[$ticket->type] ?? $ticket->type).($ticket->related_work ? ' · '.$ticket->related_work : '').' · opened '.fmt_datetime($ticket->created_at)">
        <x-status :value="['open' => 'sent', 'in_progress' => 'in_progress', 'waiting' => 'pending', 'resolved' => 'paid', 'closed' => 'void'][$ticket->status] ?? 'void'" :label="\App\Models\SupportTicket::STATUSES[$ticket->status] ?? $ticket->status" />
        @if ($ticket->isActive())
            <button type="button" class="btn-secondary" wire:click="setStatus('resolved')">Mark resolved</button>
        @else
            <button type="button" class="btn-secondary" wire:click="setStatus('open')">Reopen</button>
        @endif
    </x-page-header>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-4">
            @foreach ($messages as $message)
                <article wire:key="m-{{ $message->id }}" @class(['card p-4 sm:p-5', 'border-amber-200 bg-amber-50/60' => $message->is_internal, 'border-brand-200 bg-brand-50/40' => $message->fromTeam() && ! $message->is_internal])>
                    <header class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span class="flex items-center gap-2 font-semibold text-stone-900">
                            {{ $message->authorName() }}
                            @if ($message->is_internal) <span class="badge-amber">Internal note · not visible to customer</span>
                            @elseif ($message->fromTeam()) <span class="badge-green">Team</span>
                            @else <span class="badge-gray">Customer</span> @endif
                        </span>
                        <time class="text-xs text-stone-500">{{ fmt_datetime($message->created_at) }}</time>
                    </header>
                    <div class="text-sm leading-relaxed whitespace-pre-line text-stone-800">{{ $message->body }}</div>
                    @if ($message->attachments->isNotEmpty())
                        <ul class="mt-3 flex flex-wrap gap-2">
                            @foreach ($message->attachments as $attachment)
                                <li><a href="{{ route('support.attachments', $attachment) }}" class="btn-secondary btn-sm"><x-icon name="download" /> {{ $attachment->name }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </article>
            @endforeach

            <form wire:submit="send" @class(['card space-y-3 p-4 sm:p-5', 'border-amber-200 bg-amber-50/40' => $internal])>
                <x-field :label="$internal ? 'Internal note (team only)' : 'Reply to customer'" name="reply">
                    <textarea class="input" rows="5" wire:model="reply" maxlength="10000" placeholder="{{ $internal ? 'Notes for the team…' : 'Write your reply. The customer is emailed a copy.' }}"></textarea>
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
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm text-stone-700"><input type="checkbox" class="rounded border-stone-300" wire:model.live="internal"> Internal note only</label>
                    <div class="flex items-center gap-2">
                        @unless ($internal)
                            <label class="text-xs font-semibold text-stone-500 uppercase" for="replyStatus">Then set status</label>
                            <select id="replyStatus" class="input w-auto" wire:model="replyStatus">
                                @foreach (\App\Models\SupportTicket::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                            </select>
                        @endunless
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="files,send">{{ $internal ? 'Add note' : 'Send reply' }}</button>
                    </div>
                </div>
            </form>
        </div>

        <aside class="space-y-5">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Ticket</h2></div>
                <div class="space-y-4 p-4">
                    <x-field label="Status">
                        <select class="input" aria-label="Status" wire:change="setStatus($event.target.value)">
                            @foreach (\App\Models\SupportTicket::STATUSES as $key => $label) <option value="{{ $key }}" @selected($ticket->status === $key)>{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                    <x-field label="Priority">
                        <select class="input" aria-label="Priority" wire:change="setPriority($event.target.value)">
                            @foreach (\App\Models\SupportTicket::PRIORITIES as $key => $label) <option value="{{ $key }}" @selected($ticket->priority === $key)>{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                    <x-field label="Assigned to">
                        <select class="input" aria-label="Assigned to" wire:change="assign($event.target.value)">
                            <option value="">Unassigned</option>
                            @foreach ($team as $member) <option value="{{ $member->id }}" @selected($ticket->assigned_to === $member->id)>{{ $member->name }}</option> @endforeach
                        </select>
                    </x-field>
                    @if ($ticket->rating) <p class="text-sm text-stone-700">Customer rating: <strong>{{ $ticket->rating }}/5</strong></p> @endif
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Customer</h2></div>
                <div class="space-y-3 p-4 text-sm">
                    <div>
                        <p class="font-semibold text-stone-900">{{ $ticket->portalUser?->name ?? 'Deleted account' }}</p>
                        <p class="break-all text-stone-600">{{ $ticket->portalUser?->email }}</p>
                        @if ($ticket->portalUser?->phone) <p class="text-stone-600">{{ $ticket->portalUser->phone }}</p> @endif
                        @if ($ticket->portalUser?->company) <p class="text-stone-600">{{ $ticket->portalUser->company }}</p> @endif
                    </div>
                    <x-field label="CRM client" hint="Links this ticket to a client record.">
                        <select class="input" aria-label="CRM client" wire:change="linkClient($event.target.value)">
                            <option value="">Not linked</option>
                            @foreach ($clients as $c) <option value="{{ $c->id }}" @selected($ticket->client_id === $c->id)>{{ $c->name }}</option> @endforeach
                        </select>
                    </x-field>
                    @if ($ticket->client && auth()->user()->can('clients.view'))
                        <a class="link text-xs" href="{{ route('clients.show', $ticket->client) }}">Open {{ $ticket->client->name }} →</a>
                    @endif
                </div>
            </section>

            @if ($otherTickets->isNotEmpty())
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Other tickets from this customer</h2></div>
                    @foreach ($otherTickets as $other)
                        <a href="{{ route('support.tickets.show', $other) }}" class="block border-b border-stone-100 px-4 py-2.5 text-sm last:border-b-0 hover:bg-stone-50">
                            <span class="font-mono text-xs text-brand-700">{{ $other->number }}</span> · <span class="text-xs text-stone-500">{{ \App\Models\SupportTicket::STATUSES[$other->status] ?? $other->status }}</span>
                            <span class="block truncate text-stone-900">{{ $other->subject }}</span>
                        </a>
                    @endforeach
                </section>
            @endif
        </aside>
    </div>
</div>
