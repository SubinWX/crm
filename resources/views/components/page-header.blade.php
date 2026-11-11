@props(['title', 'subtitle' => null])
<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div class="min-w-0">
        <h1 class="truncate text-xl font-semibold text-stone-900">{{ $title }}</h1>
        @if ($subtitle) <p class="mt-0.5 text-sm text-stone-500">{{ $subtitle }}</p> @endif
    </div>
    <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
</div>
