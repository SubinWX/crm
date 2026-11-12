{{--
    Banner across the top of a public page: background image (or the brand colour), darkened so
    the white heading stays readable. Image, wording, height and alignment come from that page's
    Page Settings; a page can pass its own heading and text (a job title, for example).
--}}
@props(['page', 'heading' => null, 'text' => null, 'compact' => false, 'eyebrow' => null, 'width' => 'max-w-6xl'])
@php
    $design = \App\Support\PublicPage::for($page);
    $image = $design->bannerUrl();
    $overlay = max(0, min(90, (int) $design->get('banner_overlay'))) / 100;
    $centered = $design->get('banner_align') === 'center';
    $padding = $compact
        ? 'pt-10 pb-20 sm:pt-12 sm:pb-24'
        : (['sm' => 'pt-12 pb-24 sm:pt-14 sm:pb-28', 'md' => 'pt-16 pb-28 sm:pt-24 sm:pb-36', 'lg' => 'pt-24 pb-36 sm:pt-36 sm:pb-48'][$design->get('banner_height')] ?? 'pt-16 pb-28 sm:pt-24 sm:pb-36');
    $heading ??= $design->get('banner_heading');
    $text ??= $design->get('banner_text');
@endphp
<section class="relative isolate overflow-hidden bg-brand-800 text-white">
    @if ($image)
        <img src="{{ $image }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover" fetchpriority="high">
        <div class="absolute inset-0 -z-10" style="background: rgb(12 18 16 / {{ $overlay }});"></div>
    @else
        {{-- No photo yet: a brand-coloured backdrop with a soft pattern. --}}
        <div class="absolute inset-0 -z-20 bg-gradient-to-br from-brand-700 via-brand-800 to-ink-900"></div>
        <div class="absolute inset-0 -z-10 opacity-[0.07]" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 22px 22px;"></div>
        <div class="absolute -top-24 -right-16 -z-10 size-96 rounded-full bg-brand-500/20 blur-3xl"></div>
    @endif
    <div @class(['mx-auto px-4 sm:px-6', $width, $padding, 'text-center' => $centered])>
        @if ($eyebrow) <div @class(['mb-3 text-sm', 'flex justify-center' => $centered])>{{ $eyebrow }}</div> @endif
        <h1 @class(['font-semibold tracking-tight text-balance', 'text-3xl sm:text-4xl' => $compact, 'text-4xl sm:text-5xl' => ! $compact, 'mx-auto' => $centered, 'max-w-3xl'])>{{ $heading }}</h1>
        @if (filled($text)) <p @class(['mt-3 max-w-2xl text-base leading-relaxed text-white/85 sm:text-lg', 'mx-auto' => $centered])>{{ $text }}</p> @endif
        @if (trim($slot) !== '') <div @class(['mt-5 flex flex-wrap gap-2', 'justify-center' => $centered])>{{ $slot }}</div> @endif
    </div>
</section>
