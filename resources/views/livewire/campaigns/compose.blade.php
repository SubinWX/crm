<div>
    <x-page-header title="New bulk message" subtitle="Write it once; each client gets their own copy with their name filled in.">
        <a href="{{ route('campaigns.index') }}" class="btn-secondary">Back</a>
    </x-page-header>

    @unless ($ready)
        <div class="mb-5 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            <strong>{{ ['email' => 'No email server is connected yet.', 'sms' => 'No SMS service is connected yet.'][$channel] ?? 'WhatsApp is not set up yet.' }}</strong>
            You can write the message, but it cannot be sent until this is set up in <a href="{{ route('settings.messaging') }}" class="underline">Administration → Email, WhatsApp &amp; SMS</a>.
        </div>
    @endunless

    <form wire:submit="send" class="grid items-start gap-5 xl:grid-cols-[1fr_24rem]">
        <div class="space-y-5">
            <div class="card p-5">
                <h2 class="card-title mb-3">1. The message</h2>
                <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                    <x-field label="Name (for your own list)" name="name" required><input class="input" type="text" wire:model="name" placeholder="e.g. Onam greetings 2026"></x-field>
                    <x-field label="Send by" name="channel" required>
                        <select class="input" wire:model.live="channel">
                            @foreach (\App\Models\Campaign::CHANNELS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                </div>
                @if ($channel === 'email')
                    <x-field label="Subject" name="subject" required class="mt-4"><input class="input" type="text" wire:model.blur="subject" placeholder="e.g. Happy Onam from all of us"></x-field>
                    <x-field label="Message" name="body" required class="mt-4">
                        <x-editor model="body" :value="$body" min-height="14rem" label="Message" />
                    </x-field>
                @else
                    @if ($needsTemplate)
                        <x-field label="WhatsApp template name" name="whatsappTemplate" required class="mt-4" hint="WhatsApp (Meta) only delivers messages that use a template approved in WhatsApp Manager. The template’s first value {{ '{' }}{{ '{' }}1}} is filled with the contact’s name. The text below is kept for your records.">
                            <input class="input" type="text" wire:model.blur="whatsappTemplate" placeholder="e.g. festival_greeting">
                        </x-field>
                    @endif
                    @if ($channel === 'sms')
                        <x-field label="DLT template ID" name="whatsappTemplate" class="mt-4" hint="The ID of the registered template this message matches. Needed by most Indian SMS services; leave empty if yours does not use one.">
                            <input class="input" type="text" wire:model.blur="whatsappTemplate" placeholder="e.g. 1107161234567890123">
                        </x-field>
                    @endif
                    <x-field label="Message" name="body" required class="mt-4" :hint="$channel === 'sms' ? 'Plain text, up to 640 characters. One text message holds 160; longer ones are charged as several. With DLT, the wording must match the registered template exactly.' : 'Plain text, up to 1,500 characters.'">
                        <textarea class="input" rows="{{ $channel === 'sms' ? 4 : 7 }}" wire:model.live.debounce.400ms="body" placeholder="Hello {contact}, …"></textarea>
                        @if ($channel === 'sms') <p class="mt-1 text-xs text-stone-500">{{ mb_strlen($body) }} characters · about {{ max(1, (int) ceil(mb_strlen($body) / 153)) }} {{ mb_strlen($body) > 160 ? 'text messages' : 'text message' }} per client</p> @endif
                    </x-field>
                @endif
                <p class="mt-2 text-xs text-stone-500">Filled in for each client:
                    @foreach (\App\Models\Campaign::PLACEHOLDERS as $tag => $meaning) <code class="rounded bg-stone-100 px-1">{{ $tag }}</code> {{ $meaning }}@if (! $loop->last), @endif @endforeach.</p>
            </div>

            <div class="card p-5">
                <h2 class="card-title mb-3">2. Who gets it</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field label="Client status" name="statuses">
                        <div class="space-y-1 text-sm">
                            @foreach (\App\Models\Client::STATUSES as $key => $label)
                                <label class="flex items-center gap-2"><input type="checkbox" value="{{ $key }}" wire:model.live="statuses"> {{ $label }}</label>
                            @endforeach
                        </div>
                    </x-field>
                    <div class="space-y-4">
                        <x-field label="Industry">
                            <select class="input" wire:model.live="industry"><option value="">Any</option>@foreach ($industries as $i) <option value="{{ $i->id }}">{{ $i->name }}</option> @endforeach</select>
                        </x-field>
                        <x-field label="Work type">
                            <select class="input" wire:model.live="workType"><option value="">Any</option>@foreach ($workTypes as $w) <option value="{{ $w->id }}">{{ $w->name }}</option> @endforeach</select>
                        </x-field>
                    </div>
                    <x-field label="Renewal coming up" hint="Clients with a renewal or hosting payment due in that time.">
                        <select class="input" wire:model.live="renewalDays">
                            <option value="">Does not matter</option><option value="30">In the next 30 days</option><option value="60">In the next 60 days</option><option value="90">In the next 90 days</option>
                        </select>
                    </x-field>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-semibold text-stone-900">{{ $selectedCount }} of {{ $reachable->count() }} {{ $reachable->count() === 1 ? 'client' : 'clients' }} selected</p>
                    <div class="flex gap-2">
                        <button type="button" class="btn-ghost btn-sm" wire:click="$set('excluded', [])">Select all</button>
                        <button type="button" class="btn-ghost btn-sm" wire:click="$set('excluded', {{ $reachable->pluck('id')->map(fn ($id) => (string) $id)->toJson() }})">Select none</button>
                    </div>
                </div>
                <div class="mt-2 max-h-72 overflow-y-auto rounded-md border border-stone-200">
                    @forelse ($reachable as $c)
                        <label class="flex cursor-pointer items-center gap-3 border-b border-stone-100 px-3 py-2 text-sm last:border-b-0 hover:bg-stone-50" wire:key="aud-{{ $c->id }}">
                            <input type="checkbox" @checked(! in_array((string) $c->id, array_map('strval', $excluded), true))
                                   x-on:change="$wire.set('excluded', $event.target.checked ? $wire.excluded.filter(id => String(id) !== '{{ $c->id }}') : [...$wire.excluded, '{{ $c->id }}'])">
                            <span class="min-w-0 flex-1 truncate font-medium text-stone-900">{{ $c->name }}@if ($c->contact_name) <span class="font-normal text-stone-500">· {{ $c->contact_name }}</span>@endif</span>
                            <span class="truncate text-xs text-stone-500">{{ $c->address }}</span>
                        </label>
                    @empty
                        <p class="px-3 py-8 text-center text-sm text-stone-500">No clients match these filters.</p>
                    @endforelse
                </div>
                @error('statuses') <p class="field-error">{{ $message }}</p> @enderror
                <p class="mt-2 text-xs text-stone-500">
                    @if ($unreachable->isNotEmpty()) {{ $unreachable->count() }} matching {{ $unreachable->count() === 1 ? 'client has' : 'clients have' }} no {{ $channel === 'email' ? 'email address' : 'mobile number' }} and {{ $unreachable->count() === 1 ? 'is' : 'are' }} left out: {{ $unreachable->take(6)->pluck('name')->implode(', ') }}{{ $unreachable->count() > 6 ? '…' : '' }}. @endif
                    @if ($optedOut) {{ $optedOut }} {{ $optedOut === 1 ? 'client has' : 'clients have' }} asked not to receive bulk messages and {{ $optedOut === 1 ? 'is' : 'are' }} never included. @endif
                    Archived clients are never included.
                </p>
            </div>
        </div>

        <div class="card p-5 xl:sticky xl:top-20">
            <h2 class="card-title mb-3">3. Check and send</h2>
            <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Preview @if ($sample) for {{ $sample->name }} @endif</p>
            <div class="mt-1 rounded-md border border-stone-200 bg-stone-50 p-3 text-sm">
                @if ($channel === 'email')
                    <p class="font-semibold text-stone-900">{{ \App\Models\Campaign::personalise($subject, $sample) ?: 'Subject' }}</p>
                    <div class="rich mt-2 text-stone-700">{!! \App\Support\RichText::render(\App\Models\Campaign::personalise((string) \App\Support\RichText::clean($body), $sample, html: true)) ?: '<span class="text-stone-400">Your message appears here.</span>' !!}</div>
                @else
                    <p class="whitespace-pre-wrap text-stone-700">{{ \App\Models\Campaign::personalise($body, $sample) ?: 'Your message appears here.' }}</p>
                @endif
            </div>
            @error('channel') <p class="field-error">{{ $message }}</p> @enderror
            <div class="mt-4 space-y-2">
                @if ($channel === 'email')
                    <button type="button" class="btn-secondary w-full justify-center" wire:click="sendTest" wire:loading.attr="disabled" wire:target="sendTest">Send a test to me</button>
                @endif
                <button type="submit" class="btn-primary w-full justify-center" wire:loading.attr="disabled" wire:target="send" @disabled(! $ready || $selectedCount === 0)
                        wire:confirm="Send this {{ ['email' => 'email', 'sms' => 'text message'][$channel] ?? 'WhatsApp message' }} to {{ $selectedCount }} {{ $selectedCount === 1 ? 'client' : 'clients' }} now? It cannot be taken back.">
                    Send to {{ $selectedCount }} {{ $selectedCount === 1 ? 'client' : 'clients' }}
                </button>
            </div>
            <p class="mt-3 text-xs text-stone-500">Messages go out one after another in the background; the list page shows the result for each client. @if ($channel === 'email') Every email has a link the client can use to stop these emails. @endif</p>
        </div>
    </form>
</div>
