<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Careers' }} · {{ \App\Support\PublicSite::siteName() }} Careers</title>
    @isset($description) <meta name="description" content="{{ $description }}"> @endisset
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-brand-head />
    @livewireStyles
</head>
<body class="flex min-h-screen flex-col bg-paper">
    <x-public.header page="careers" :home="route('careers.index')">
        <a href="{{ route('careers.index') }}" class="hidden rounded-md px-3 py-2 font-medium text-stone-600 hover:bg-stone-100 hover:text-stone-900 sm:inline">Open positions</a>
    </x-public.header>

    <main class="flex-1 pb-12">
        {{ $slot }}
    </main>

    <x-public.cta page="careers" />
    <x-public.footer />
    @livewireScripts
</body>
</html>
