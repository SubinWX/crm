{{--
    "WhatsApp" button for one reminder. It opens WhatsApp (the app, or WhatsApp Web) on a chat with
    the client's primary number and the reminder already typed, ready to press send. This needs no
    API account. The message goes from whichever WhatsApp account is signed in on that computer or phone.
    Until the WhatsApp API is connected it works as described above. Once it is connected (Settings →
    Email & WhatsApp) and a "send" action is given, the same button sends the message itself instead.
    $kind is hosting | renewal | invoice; $values fills {client} {item} {date} {amount} in the wording
    from Settings → Email & WhatsApp.
--}}
@props(['client', 'kind', 'values', 'label' => 'WhatsApp', 'send' => null])
@php
    // The primary phone, or the second one when the primary is missing or not a mobile number.
    $to = \App\Support\WhatsApp::normalizePhone($client?->phone1) ?? \App\Support\WhatsApp::normalizePhone($client?->phone2);
    $message = \App\Support\WhatsApp::compose($kind, $values + ['client' => (string) $client?->name]);
@endphp
@if ($send && \App\Support\WhatsApp::enabled())
    {{-- The WhatsApp API is connected: the CRM sends it and records it. "$send" is the Livewire action to call. --}}
    <button type="button" wire:click="{{ $send }}" wire:confirm="Send this WhatsApp reminder to {{ $client?->name }} ({{ $client?->phone1 ?: ($client?->phone2 ?: 'no phone saved') }})?"
            {{ $attributes->class('btn-secondary btn-sm') }} title="Sent for you through the connected WhatsApp API and recorded in the message log.">
        <x-social-icon name="whatsapp" class="size-4 text-emerald-600" /> Send {{ $label }}
    </button>
@elseif ($to)
    <a href="https://wa.me/{{ $to }}?text={{ rawurlencode($message) }}" target="_blank" rel="noopener noreferrer"
       {{ $attributes->class('btn-secondary btn-sm') }} title="Opens WhatsApp with this reminder typed for {{ $client->name }} (+{{ $to }}). You press send.">
        <x-social-icon name="whatsapp" class="size-4 text-emerald-600" /> {{ $label }}
    </a>
@else
    {{-- No usable number on the client record: WhatsApp opens with the message typed and asks who to send it to. --}}
    <a href="https://wa.me/?text={{ rawurlencode($message) }}" target="_blank" rel="noopener noreferrer"
       {{ $attributes->class('btn-secondary btn-sm') }} title="No mobile number is saved for this client. WhatsApp opens with the reminder typed and you choose the contact.">
        <x-social-icon name="whatsapp" class="size-4 text-stone-500" /> {{ $label }}
    </a>
@endif
