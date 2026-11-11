{{-- $totals from Quote::computeTotals, $currency, $discountPercent, $taxRate --}}
<div class="grid gap-4 {{ count($totals) > 1 ? 'sm:grid-cols-2' : '' }}">
    @forelse ($totals as $frequency => $t)
        <div class="rounded-lg border border-stone-200 bg-white p-4 text-sm">
            <p class="mb-2 text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $frequency === 'one_time' ? 'One-time charges' : 'Recurring · '.\App\Models\Quote::FREQUENCIES[$frequency] }}</p>
            <div class="flex justify-between"><span class="text-stone-600">Subtotal</span><span class="tabular-nums">{{ money($t['subtotal'], $currency) }}</span></div>
            @if ($t['discount'] > 0) <div class="flex justify-between"><span class="text-stone-600">Discount ({{ (float) $discountPercent }}%)</span><span class="tabular-nums">− {{ money($t['discount'], $currency) }}</span></div> @endif
            @if ((float) $taxRate > 0) <div class="flex justify-between"><span class="text-stone-600">Tax ({{ (float) $taxRate }}%)</span><span class="tabular-nums">{{ money($t['tax'], $currency) }}</span></div> @endif
            <div class="mt-1.5 flex justify-between border-t border-stone-200 pt-1.5 text-base font-semibold text-stone-900"><span>Total</span><span class="tabular-nums">{{ money($t['total'], $currency) }}{{ \App\Models\Quote::FREQUENCY_SUFFIX[$frequency] }}</span></div>
        </div>
    @empty
        <p class="text-sm text-stone-500">Add line items to see totals.</p>
    @endforelse
</div>
