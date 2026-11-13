<div @if ($sending) wire:poll.5s @endif>
    <x-page-header title="Bulk Messages" subtitle="Festival greetings, offers and renewal notices sent to a chosen group of clients by email or WhatsApp.">
        <a href="{{ route('campaigns.create') }}" class="btn-primary"><x-icon name="plus" /> New bulk message</a>
    </x-page-header>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Sent by</th><th>Date</th><th class="num">Clients</th><th class="num">Sent</th><th class="num">Failed</th><th class="num">Skipped</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($campaigns as $c)
                        <tr wire:key="campaign-{{ $c->id }}">
                            <td><button type="button" class="cursor-pointer text-left font-medium text-stone-900 hover:underline" wire:click="show({{ $c->id }})">{{ $c->name }}</button>
                                <p class="text-xs text-stone-500">{{ \App\Models\Campaign::CHANNELS[$c->channel] ?? $c->channel }}@if ($c->subject) · {{ $c->subject }}@endif</p></td>
                            <td>{{ $c->creator?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ fmt_datetime($c->created_at) }}</td>
                            <td class="num">{{ $c->total }}</td>
                            <td class="num">{{ $c->sent_count }}</td>
                            <td class="num @if ($c->failed_count) font-semibold text-red-700 @endif">{{ $c->failed_count }}</td>
                            <td class="num">{{ $c->skipped_count }}</td>
                            <td>@if ($c->queued_count) <span class="badge-amber">Sending · {{ $c->queued_count }} left</span> @elseif ($c->failed_count) <span class="badge-red">Finished with failures</span> @else <span class="badge-green">Sent</span> @endif</td>
                            <td class="text-right whitespace-nowrap"><button type="button" class="btn-ghost btn-sm" wire:click="show({{ $c->id }})">Details</button></td>
                        </tr>
                    @empty
                        <x-empty :colspan="9">No bulk messages have been sent yet. Press <strong>New bulk message</strong> to write the first one.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $campaigns->links() }}</div>

    @if ($open)
        <x-modal :title="$open->name" size="max-w-3xl">
            <div class="space-y-4">
                <div class="rounded-md border border-stone-200 bg-stone-50 p-3 text-sm">
                    @if ($open->subject) <p class="font-semibold text-stone-900">{{ $open->subject }}</p> @endif
                    @if ($open->channel === 'email') <div class="rich mt-1 text-stone-700">{!! \App\Support\RichText::render($open->body) !!}</div>
                    @else <p class="whitespace-pre-wrap text-stone-700">{{ $open->body }}</p> @endif
                    @if ($open->whatsapp_template) <p class="mt-2 text-xs text-stone-500">WhatsApp template: {{ $open->whatsapp_template }}</p> @endif
                </div>
                <div class="table-wrap max-h-80 overflow-y-auto rounded-md border border-stone-200">
                    <table class="table">
                        <thead><tr><th>Client</th><th>Sent to</th><th>Result</th></tr></thead>
                        <tbody>
                            @foreach ($open->recipients as $r)
                                <tr wire:key="r-{{ $r->id }}">
                                    <td>{{ $r->name }}</td>
                                    <td class="text-xs">{{ $r->recipient ?: '—' }}</td>
                                    <td><span class="{{ ['sent' => 'badge-green', 'failed' => 'badge-red', 'skipped' => 'badge-gray', 'queued' => 'badge-amber'][$r->status] ?? 'badge-gray' }}">{{ \App\Models\CampaignRecipient::STATUSES[$r->status] ?? $r->status }}</span>
                                        @if ($r->error) <p class="mt-0.5 text-xs text-stone-500">{{ $r->error }}</p> @endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-between gap-2">
                    @if ($open->recipients->where('status', 'failed')->isNotEmpty())
                        <button type="button" class="btn-secondary" wire:click="retryFailed({{ $open->id }})" wire:confirm="Send again to the clients whose message failed?">Send again to failed</button>
                    @else <span></span> @endif
                    <button type="button" class="btn-secondary" wire:click="closeModal">Close</button>
                </div>
            </div>
        </x-modal>
    @endif
</div>
