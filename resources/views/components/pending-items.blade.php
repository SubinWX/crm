{{-- Dashboard: what is waiting for the signed-in person, highlighted so it is the first thing seen after sign-in. --}}
@php $items = \App\Support\PendingItems::for(auth()->user()); @endphp
@if ($items === [])
    <div class="mb-5 flex items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-900" role="status">
        <x-icon name="check" class="size-5" /> You are all caught up. Nothing is waiting for you.
    </div>
@else
    <section class="mb-5 rounded-lg border-2 border-amber-300 bg-amber-50 p-4" aria-label="Needs your attention">
        <h2 class="flex items-center gap-2 text-sm font-semibold text-amber-950">
            <x-icon name="alert" class="size-5 text-amber-600" /> Needs your attention
            <span class="rounded-full bg-amber-600 px-2 py-0.5 text-xs font-bold text-white">{{ count($items) }}</span>
        </h2>
        <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($items as $item)
                <a href="{{ $item['url'] }}" @class(['flex items-center gap-3 rounded-md border bg-white px-3 py-2.5 text-sm transition hover:shadow', 'border-red-300' => $item['urgent'], 'border-amber-200' => ! $item['urgent']])>
                    <x-icon :name="$item['icon']" @class(['size-5', 'text-red-600' => $item['urgent'], 'text-amber-600' => ! $item['urgent']]) />
                    @if ($item['count']) <span @class(['min-w-7 rounded-full px-2 py-0.5 text-center text-sm font-bold tabular-nums', 'bg-red-600 text-white' => $item['urgent'], 'bg-amber-100 text-amber-900' => ! $item['urgent']])>{{ $item['count'] }}</span> @endif
                    <span class="min-w-0 flex-1 font-medium text-stone-900">{{ $item['label'] }}</span>
                    <span class="text-xs font-semibold text-brand-700">Open →</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
