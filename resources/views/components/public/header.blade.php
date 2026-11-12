{{--
    Header shared by every public page. Logo, logo link, the extra link and the button come from
    Administration → Public Pages; the small line under the name comes from the page itself.
    The slot holds the page's own links (Open positions, Sign in…).
--}}
@props(['page', 'home'])
@php
    $site = \App\Support\PublicSite::class;
    $design = \App\Support\PublicPage::for($page);
    $logo = $site::logoUrl();
    $logoLink = $site::get('logo_link') ?: $home;
    $link = $site::navLink();
    $button = $site::button();
@endphp
<header class="sticky top-0 z-30 border-b border-stone-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4 sm:px-6">
        <a href="{{ $logoLink }}" class="flex min-w-0 items-center gap-2.5" @if ($site::isExternal($logoLink)) rel="noopener" @endif>
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $site::siteName() }}" class="h-9 w-auto max-w-48 object-contain sm:max-w-56">
                @if ($design->get('tagline') !== '') <span class="hidden border-l border-stone-200 pl-3 text-sm font-medium text-stone-500 sm:inline">{{ $design->get('tagline') }}</span> @endif
            @else
                <x-brand-mark size="size-9" text="text-sm" />
                <span class="min-w-0 leading-tight"><span class="block truncate text-sm font-semibold text-stone-900">{{ $site::siteName() }}</span>@if ($design->get('tagline') !== '')<span class="block truncate text-xs text-stone-500">{{ $design->get('tagline') }}</span>@endif</span>
            @endif
        </a>
        <nav class="ml-auto flex shrink-0 items-center gap-1 text-sm sm:gap-2" aria-label="{{ $design->name() }}">
            {{ $slot }}
            @if ($link)
                <a href="{{ $link['url'] }}" class="hidden rounded-md px-3 py-2 font-medium text-stone-600 hover:bg-stone-100 hover:text-stone-900 md:inline" @if ($site::isExternal($link['url'])) target="_blank" rel="noopener" @endif>{{ $link['label'] }}</a>
            @endif
            @if ($button)
                <a href="{{ $button['url'] }}" class="ml-1 hidden items-center px-4 py-2 text-sm font-semibold transition hover:opacity-90 sm:inline-flex" style="{{ $button['style'] }}" @if ($button['new_tab']) target="_blank" rel="noopener" @endif>{{ $button['label'] }}</a>
            @endif
        </nav>
    </div>
</header>
