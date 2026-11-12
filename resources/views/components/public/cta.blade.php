{{--
    Call-to-action band shown above the footer: a heading, a short line and one button.
    Switched on and worded in that page's Page Settings. Renders nothing while it is off.
--}}
@props(['page'])
@php $cta = \App\Support\PublicPage::for($page)->cta(); @endphp
@if ($cta)
    @php
        [$band, $headingColor, $textColor, $buttonClass] = [
            'brand' => ['bg-brand-700', 'text-white', 'text-white/85', 'bg-white text-brand-800 hover:bg-brand-50'],
            'dark' => ['bg-ink-900', 'text-white', 'text-white/75', 'bg-brand-500 text-white hover:bg-brand-400'],
            'light' => ['bg-brand-50 border-y border-brand-100', 'text-stone-900', 'text-stone-600', 'bg-brand-600 text-white hover:bg-brand-700'],
        ][$cta['style']];
    @endphp
    <section class="{{ $band }}" aria-label="{{ $cta['heading'] }}">
        <div class="mx-auto flex max-w-6xl flex-col items-start gap-5 px-4 py-10 sm:px-6 sm:py-12 md:flex-row md:items-center md:justify-between">
            <div class="max-w-2xl">
                <h2 class="text-2xl font-semibold tracking-tight text-balance sm:text-3xl {{ $headingColor }}">{{ $cta['heading'] }}</h2>
                @if ($cta['text'] !== '') <p class="mt-2 text-base leading-relaxed {{ $textColor }}">{{ $cta['text'] }}</p> @endif
            </div>
            <a href="{{ $cta['url'] }}" class="inline-flex shrink-0 items-center gap-2 rounded-lg px-6 py-3 text-base font-semibold shadow-sm transition {{ $buttonClass }}" @if ($cta['new_tab']) target="_blank" rel="noopener" @endif>
                {{ $cta['label'] }} <span aria-hidden="true">→</span>
            </a>
        </div>
    </section>
@endif
