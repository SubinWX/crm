{{-- Colours and favicon from Settings → Branding. Placed after the stylesheet so these values win. --}}
@php $palette = \App\Support\Branding::palette(); @endphp
<style>
    :root { @foreach ($palette as $variable => $hex) {{ $variable }}: {{ $hex }}; @endforeach }
</style>
@if ($favicon = \App\Support\Branding::faviconUrl())
    <link rel="icon" href="{{ $favicon }}">
@endif
