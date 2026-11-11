@props(['colspan' => null])
@if ($colspan)
    <tr><td colspan="{{ $colspan }}" class="!py-10 text-center text-sm text-stone-500">{{ $slot }}</td></tr>
@else
    <div class="px-4 py-10 text-center text-sm text-stone-500">{{ $slot }}</div>
@endif
