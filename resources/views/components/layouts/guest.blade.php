<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Sign in' }} · {{ app_name() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-brand-head />
</head>
@php
    // Drop your own photo at public/images/auth-side.jpg (or .png / .webp) to replace the illustration.
    $sideImage = collect(['jpg', 'jpeg', 'png', 'webp'])
        ->map(fn ($ext) => "images/auth-side.{$ext}")
        ->first(fn ($path) => file_exists(public_path($path)), 'images/auth-side.svg');
    $company = app_name();
@endphp
<body class="min-h-screen bg-stone-200">
    <div class="flex min-h-screen items-center justify-center p-4 sm:p-8">
        {{-- Boxed wrapper: image on the left, form on the right --}}
        <div class="grid w-full max-w-5xl overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xl md:grid-cols-2">
            <div class="relative hidden min-h-[620px] bg-ink-900 md:block">
                @if (str_ends_with($sideImage, '.svg'))
                    <div class="absolute inset-0 [&>svg]:size-full" aria-hidden="true">{!! strtr(file_get_contents(public_path($sideImage)), [
                        '#0f4a3c' => 'var(--color-brand-800)', '#111917' => 'var(--color-ink-900)', '#1f8a70' => 'var(--color-brand-500)', '#15735c' => 'var(--color-brand-600)',
                        '#4fc3a1' => 'var(--color-brand-200)', '#eef7f4' => 'var(--color-brand-50)', '#d6ece5' => 'var(--color-brand-100)', '#aed9cc' => 'var(--color-brand-200)', '#18231f' => 'var(--color-ink-800)',
                    ]) !!}</div>
                @else
                    <img src="{{ asset($sideImage) }}" alt="" class="absolute inset-0 size-full object-cover">
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-ink-900/90 via-ink-900/10 to-ink-900/40"></div>
                <div class="relative flex h-full flex-col justify-between p-8 text-white">
                    <div class="flex items-center gap-2">
                        <x-brand-mark size="size-8" text="text-sm" />
                        <span class="text-base font-semibold">{{ $company }}</span>
                    </div>
                    <div>
                        <p class="text-2xl leading-snug font-semibold">Clients, renewals, invoices and creative — one desk.</p>
                        <p class="mt-2 text-sm text-stone-300">Internal tool · authorised team members only</p>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-center p-6 sm:p-10">
                <div class="w-full max-w-sm">
                    <div class="mb-8 flex items-center gap-2 md:hidden">
                        <x-brand-mark size="size-8" text="text-sm" />
                        <span class="text-base font-semibold text-stone-900">{{ $company }}</span>
                    </div>
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>
