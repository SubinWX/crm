<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Support' }} · {{ \App\Support\PublicSite::siteName() }} Support</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-brand-head />
    @livewireStyles
</head>
@php $portalUser = auth('portal')->user(); @endphp
<body class="flex min-h-screen flex-col bg-paper">
    <x-public.header page="support" :home="route('portal.home')">
        @if ($portalUser)
            <a href="{{ route('portal.tickets.index') }}" @class(['rounded-md px-3 py-2 font-medium', 'bg-brand-50 text-brand-800' => request()->routeIs('portal.tickets.index', 'portal.tickets.show'), 'text-stone-600 hover:bg-stone-100' => ! request()->routeIs('portal.tickets.index', 'portal.tickets.show')])>My tickets</a>
            <a href="{{ route('portal.tickets.create') }}" class="btn-primary ml-1"><x-icon name="plus" /> <span class="hidden sm:inline">New ticket</span></a>
            <span class="ml-3 hidden max-w-40 truncate text-stone-600 lg:inline">{{ $portalUser->name }}</span>
            <form method="POST" action="{{ route('portal.logout') }}" class="ml-1">@csrf <button type="submit" class="btn-ghost">Sign out</button></form>
        @else
            <a href="{{ route('portal.login') }}" class="btn-ghost">Sign in</a>
            <a href="{{ route('portal.register') }}" class="btn-primary">Create account</a>
        @endif
    </x-public.header>

    {{-- A page may put a full-width banner here; the rest of the page sits in the narrower column below it. --}}
    {{ $banner ?? '' }}

    <main @class(['mx-auto w-full max-w-5xl flex-1 px-4 pb-12 sm:px-6', 'pt-8' => ! isset($banner), 'relative z-10 -mt-14 sm:-mt-16' => isset($banner)])>
        @if (session('status'))
            <div @class(['mb-5 rounded-md border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800', 'shadow-sm' => isset($banner)]) role="status">{{ session('status') }}</div>
        @endif
        {{ $slot }}
    </main>

    @isset($banner) <x-public.cta page="support" /> @endisset
    <x-public.footer />

    <div x-data="{ toasts: [] }" class="pointer-events-none fixed right-4 bottom-4 z-50 space-y-2"
         @toast.window="const t = { id: Date.now(), message: $event.detail.message }; toasts.push(t); setTimeout(() => toasts = toasts.filter(x => x.id !== t.id), 4000)">
        <template x-for="t in toasts" :key="t.id"><div class="pointer-events-auto rounded-md bg-ink-800 px-4 py-2.5 text-sm font-medium text-white shadow-lg" role="status" x-text="t.message"></div></template>
    </div>
    @livewireScripts
</body>
</html>
