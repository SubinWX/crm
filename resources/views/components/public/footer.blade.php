{{-- Footer shared by every public page: copyright, Terms and Privacy links, social media. Set in Administration → Public Pages. --}}
@php $site = \App\Support\PublicSite::class; @endphp
<footer class="border-t border-stone-200 bg-white">
    <div class="mx-auto flex max-w-6xl flex-col items-center gap-4 px-4 py-6 text-sm text-stone-500 sm:px-6 md:flex-row md:justify-between">
        <p class="text-center md:text-left">{{ $site::copyright() }}</p>
        @if ($links = $site::footerLinks())
            <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-1" aria-label="Legal">
                @foreach ($links as $link)
                    <a href="{{ $link['url'] }}" class="hover:text-stone-900 hover:underline" @if ($site::isExternal($link['url'])) target="_blank" rel="noopener" @endif>{{ $link['label'] }}</a>
                @endforeach
            </nav>
        @endif
        @if ($socials = $site::socials())
            <div class="flex items-center gap-2">
                @foreach ($socials as $network => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="grid size-9 place-items-center rounded-full bg-stone-100 text-stone-600 transition hover:bg-brand-600 hover:text-white" aria-label="{{ $site::SOCIALS[$network] }}" title="{{ $site::SOCIALS[$network] }}">
                        <x-social-icon :name="$network" class="size-4" />
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</footer>
