@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'href' => null])
<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['card block p-4', 'hover:border-stone-300' => $href, 'border-red-200 bg-red-50/50' => $tone === 'danger']) }}>
    <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $label }}</p>
    <p @class(['mt-1 text-2xl font-semibold tabular-nums', 'text-red-700' => $tone === 'danger', 'text-stone-900' => $tone !== 'danger'])>{{ $value }}</p>
    @if ($hint) <p class="mt-0.5 text-xs text-stone-500">{{ $hint }}</p> @endif
</{{ $href ? 'a' : 'div' }}>
