@props(['title', 'close' => 'closeModal', 'size' => 'max-w-2xl'])
<div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink-900/50 p-4 sm:p-8" role="dialog" aria-modal="true"
     x-data x-on:keydown.escape.window="$wire.{{ $close }}()">
    <div class="card w-full {{ $size }} shadow-xl" x-on:click.outside="$wire.{{ $close }}()">
        <div class="card-header">
            <h2 class="card-title text-base">{{ $title }}</h2>
            <button type="button" class="btn-ghost" wire:click="{{ $close }}" aria-label="Close"><x-icon name="x" class="size-5" /></button>
        </div>
        <div class="p-4 sm:p-5">{{ $slot }}</div>
    </div>
</div>
