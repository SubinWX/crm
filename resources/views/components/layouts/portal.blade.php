<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Support' }} · {{ app_name() }} Support</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-brand-head />
    @livewireStyles
</head>
@php $portalUser = auth('portal')->user(); $company = app_name(); @endphp
<body class="flex min-h-screen flex-col bg-paper">
    <header class="border-b border-stone-200 bg-white">
        <div class="mx-auto flex h-16 max-w-5xl items-center gap-4 px-4 sm:px-6">
            <a href="{{ route('portal.home') }}" class="flex items-center gap-2.5">
                <x-brand-mark size="size-9" text="text-sm" />
                <span class="leading-tight"><span class="block text-sm font-semibold text-stone-900">{{ $company }}</span><span class="block text-xs text-stone-500">Support Portal</span></span>
            </a>
            <nav class="ml-auto flex items-center gap-1 text-sm" aria-label="Portal">
                @if ($portalUser)
                    <a href="{{ route('portal.tickets.index') }}" @class(['rounded-md px-3 py-2 font-medium', 'bg-brand-50 text-brand-800' => request()->routeIs('portal.tickets.index', 'portal.tickets.show'), 'text-stone-600 hover:bg-stone-100' => ! request()->routeIs('portal.tickets.index', 'portal.tickets.show')])>My tickets</a>
                    <a href="{{ route('portal.tickets.create') }}" class="btn-primary ml-1"><x-icon name="plus" /> <span class="hidden sm:inline">New ticket</span></a>
                    <span class="ml-3 hidden max-w-40 truncate text-stone-600 md:inline">{{ $portalUser->name }}</span>
                    <form method="POST" action="{{ route('portal.logout') }}" class="ml-1">@csrf <button type="submit" class="btn-ghost">Sign out</button></form>
                @else
                    <a href="{{ route('portal.login') }}" class="btn-ghost">Sign in</a>
                    <a href="{{ route('portal.register') }}" class="btn-primary">Create account</a>
                @endif
            </nav>
        </div>
    </header>

    <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:px-6">
        @if (session('status'))
            <div class="mb-5 rounded-md border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800" role="status">{{ session('status') }}</div>
        @endif
        {{ $slot }}
    </main>

    <footer class="border-t border-stone-200 bg-white">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-2 px-4 py-4 text-xs text-stone-500 sm:px-6">
            <span>© {{ date('Y') }} {{ $company }}</span>
            <span>{{ collect([setting('company_email'), setting('company_phone')])->filter()->implode(' · ') }}</span>
        </div>
    </footer>

    <div x-data="{ toasts: [] }" class="pointer-events-none fixed right-4 bottom-4 z-50 space-y-2"
         @toast.window="const t = { id: Date.now(), message: $event.detail.message }; toasts.push(t); setTimeout(() => toasts = toasts.filter(x => x.id !== t.id), 4000)">
        <template x-for="t in toasts" :key="t.id"><div class="pointer-events-auto rounded-md bg-ink-800 px-4 py-2.5 text-sm font-medium text-white shadow-lg" role="status" x-text="t.message"></div></template>
    </div>
    @livewireScripts
</body>
</html>
