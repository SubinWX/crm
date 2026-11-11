@props(['size' => 'size-7', 'text' => 'text-xs'])
{{-- The app icon (or, without one, the logo), or the first letter of the app name on the brand colour. --}}
@if ($logo = \App\Support\Branding::iconUrl())
    <img src="{{ $logo }}" alt="{{ app_name() }} logo" {{ $attributes->class([$size, 'shrink-0 rounded-md bg-white object-contain']) }}>
@else
    <span aria-hidden="true" {{ $attributes->class([$size, $text, 'grid shrink-0 place-items-center rounded-md bg-brand-500 font-bold text-white']) }}>{{ mb_strtoupper(mb_substr(app_name(), 0, 1)) }}</span>
@endif
