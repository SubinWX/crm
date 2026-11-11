@props(['src' => null, 'name' => '', 'size' => 'size-9', 'rounded' => 'rounded-full', 'fit' => 'object-cover'])
{{-- Image when one is uploaded, otherwise the initials. --}}
@php
    $initials = str($name)->explode(' ')->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" {{ $attributes->class([$size, $rounded, $fit, 'shrink-0 border border-stone-200 bg-white']) }}>
@else
    <span aria-hidden="true" {{ $attributes->class([$size, $rounded, 'grid shrink-0 place-items-center bg-brand-100 text-xs font-semibold text-brand-800']) }}>{{ $initials ?: '?' }}</span>
@endif
