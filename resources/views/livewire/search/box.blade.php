{{-- Desktop: a box in the top bar. Phone: a magnifier that opens the box under the bar. "/" jumps to it; Esc closes. --}}
<div class="md:relative" x-data="{ open: false, bar: false }" @click.outside="open = false; bar = false" @keydown.escape="open = false; bar = false; $refs.input.blur()"
     @keydown.window="if ($event.key === '/' && ! ['INPUT', 'TEXTAREA', 'SELECT', 'TRIX-EDITOR'].includes(document.activeElement.tagName) && ! document.activeElement.isContentEditable) { $event.preventDefault(); bar = true; $nextTick(() => $refs.input.focus()) }">
    <button type="button" class="btn-ghost md:hidden" @click="bar = ! bar; if (bar) $nextTick(() => $refs.input.focus())" aria-label="Search"><x-icon name="search" class="size-5" /></button>

    <div class="absolute inset-x-0 top-14 z-30 border-b border-stone-200 bg-white p-3 md:static md:block md:border-0 md:bg-transparent md:p-0" :class="bar ? 'block' : 'hidden md:block'">
        <div class="relative md:w-64 lg:w-80">
            <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
            <input x-ref="input" type="search" class="input pl-9" placeholder="Search clients, invoices, projects…" aria-label="Search everything" autocomplete="off"
                   wire:model.live.debounce.250ms="q" @focus="open = true" @input="open = true">
        </div>

        @if ($searched)
            <div x-show="open" x-cloak class="card mt-2 max-h-[70vh] overflow-y-auto shadow-xl md:absolute md:right-0 md:w-[26rem]" role="listbox" aria-label="Search results">
                @forelse ($groups as $group => $items)
                    <p class="border-b border-stone-100 bg-stone-50 px-3 py-1.5 text-[11px] font-semibold tracking-wide text-stone-500 uppercase">{{ $group }}</p>
                    @foreach ($items as $item)
                        <a href="{{ $item['url'] }}" class="block border-b border-stone-100 px-3 py-2 last:border-b-0 hover:bg-brand-50 focus:bg-brand-50 focus:outline-none" role="option">
                            <span class="block truncate text-sm font-medium text-stone-900">{{ $item['title'] }}</span>
                            @if ($item['sub'] !== '') <span class="block truncate text-xs text-stone-500">{{ $item['sub'] }}</span> @endif
                        </a>
                    @endforeach
                @empty
                    <p class="px-3 py-6 text-center text-sm text-stone-500">Nothing found for “{{ $q }}”.</p>
                @endforelse
            </div>
        @endif
    </div>
</div>
