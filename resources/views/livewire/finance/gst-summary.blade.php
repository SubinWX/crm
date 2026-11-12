<div>
    <x-page-header title="GST Summary" subtitle="Invoiced sales for one month with the CGST, SGST and IGST split. Send the downloads to your accountant for GSTR-1 and GSTR-3B.">
        <a href="{{ route('gst.export', ['month' => $month, 'type' => 'invoices']) }}" class="btn-secondary"><x-icon name="download" /> Invoice register (CSV)</a>
        <a href="{{ route('gst.export', ['month' => $month, 'type' => 'hsn']) }}" class="btn-secondary"><x-icon name="download" /> HSN summary (CSV)</a>
    </x-page-header>

    @unless ($gstOn)
        <div class="mb-5 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
            <p class="font-semibold">GST is not switched on yet.</p>
            <p>Choose your company’s state in <a class="link" href="{{ route('settings.index') }}">Settings → General</a>. New invoices will then carry the place of supply, HSN/SAC codes and the CGST/SGST/IGST split.</p>
        </div>
    @endunless

    <div class="mb-5 flex flex-wrap items-center gap-2">
        <button type="button" class="btn-secondary" wire:click="shift(-1)" aria-label="Previous month">←</button>
        <input type="month" class="input w-auto" wire:model.live="month" aria-label="Month">
        <button type="button" class="btn-secondary" wire:click="shift(1)" aria-label="Next month">→</button>
        <span class="ml-2 text-sm text-stone-600">{{ $report->start->format('F Y') }} · {{ $totals['count'] }} {{ $totals['count'] === 1 ? 'invoice' : 'invoices' }} issued</span>
    </div>

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat label="Taxable value" :value="money($totals['taxable'])" hint="Before tax, after discounts" />
        <x-stat label="CGST" :value="money($totals['cgst'])" hint="Sales within your state" />
        <x-stat label="SGST" :value="money($totals['sgst'])" hint="Sales within your state" />
        <x-stat label="IGST" :value="money($totals['igst'])" hint="Sales to other states" />
        <x-stat label="Total GST collected" :value="money($totals['tax'])" :hint="'Invoice value '.money($totals['total'])" />
    </div>

    @if ($unsplit)
        <p class="mb-5 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ $unsplit }} {{ $unsplit === 1 ? 'invoice has' : 'invoices have' }} tax but no CGST/SGST/IGST split, because {{ $unsplit === 1 ? 'it was' : 'they were' }} issued before GST was switched on. {{ $unsplit === 1 ? 'It is' : 'They are' }} listed below with the tax in the “Total tax” column only.</p>
    @endif

    <div class="mb-5 grid gap-5 xl:grid-cols-2">
        <section class="card">
            <div class="card-header"><h2 class="card-title">By type of client</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Type</th><th class="num">Invoices</th><th class="num">Taxable value</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th></tr></thead>
                    <tbody>
                        @foreach (['b2b' => 'B2B (client has a GSTIN)', 'b2c' => 'B2C (no GSTIN)'] as $key => $label)
                            <tr><td>{{ $label }}</td><td class="num">{{ $buyers[$key]['count'] }}</td><td class="num">{{ money($buyers[$key]['taxable']) }}</td><td class="num">{{ money($buyers[$key]['cgst']) }}</td><td class="num">{{ money($buyers[$key]['sgst']) }}</td><td class="num">{{ money($buyers[$key]['igst']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <section class="card">
            <div class="card-header"><h2 class="card-title">By GST rate</h2></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Rate</th><th class="num">Invoices</th><th class="num">Taxable value</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th></tr></thead>
                    <tbody>
                        @forelse ($rates as $r)
                            <tr><td>{{ $r['rate'] }}%</td><td class="num">{{ $r['count'] }}</td><td class="num">{{ money($r['taxable']) }}</td><td class="num">{{ money($r['cgst']) }}</td><td class="num">{{ money($r['sgst']) }}</td><td class="num">{{ money($r['igst']) }}</td></tr>
                        @empty
                            <x-empty colspan="6">No invoices issued in this month.</x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="card mb-5">
        <div class="card-header"><h2 class="card-title">HSN / SAC summary</h2><span class="text-xs text-stone-500">Discounts are spread across each invoice’s lines</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>HSN / SAC</th><th class="num">Rate</th><th class="num">Quantity</th><th class="num">Taxable value</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th><th class="num">Total tax</th></tr></thead>
                <tbody>
                    @forelse ($hsn as $h)
                        <tr><td class="font-mono">{{ $h['hsn'] }}</td><td class="num">{{ $h['rate'] }}%</td><td class="num">{{ rtrim(rtrim(number_format($h['quantity'], 2), '0'), '.') }}</td><td class="num">{{ money($h['taxable']) }}</td><td class="num">{{ money($h['cgst']) }}</td><td class="num">{{ money($h['sgst']) }}</td><td class="num">{{ money($h['igst']) }}</td><td class="num">{{ money($h['tax']) }}</td></tr>
                    @empty
                        <x-empty colspan="8">Nothing to summarise.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Invoices in {{ $report->start->format('F Y') }}</h2><span class="text-xs text-stone-500">Drafts and voided invoices are not included</span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Date</th><th>Invoice</th><th>Client</th><th>GSTIN</th><th>Place of supply</th><th class="num">Taxable value</th><th class="num">CGST</th><th class="num">SGST</th><th class="num">IGST</th><th class="num">Total tax</th><th class="num">Invoice value</th></tr></thead>
                <tbody>
                    @forelse ($report->invoices as $i)
                        <tr wire:key="gst-{{ $i->id }}">
                            <td class="whitespace-nowrap">{{ fmt_date($i->invoice_date) }}</td>
                            <td><a class="link font-mono" href="{{ route('invoices.show', $i) }}">{{ $i->number }}</a></td>
                            <td class="max-w-56 truncate">{{ $i->client->name }}</td>
                            <td class="font-mono text-xs">{{ $i->client_gstin ?: '—' }}</td>
                            <td class="whitespace-nowrap">{{ \App\Support\Gst::stateName($i->place_of_supply) }}</td>
                            <td class="num">{{ money($i->taxable_value) }}</td><td class="num">{{ money($i->cgst_amount) }}</td><td class="num">{{ money($i->sgst_amount) }}</td><td class="num">{{ money($i->igst_amount) }}</td>
                            <td class="num">{{ money($i->tax_amount) }}</td><td class="num font-medium text-stone-900">{{ money($i->total) }}</td>
                        </tr>
                    @empty
                        <x-empty colspan="11">No invoices were issued in this month.</x-empty>
                    @endforelse
                </tbody>
                @if ($totals['count'])
                    <tfoot><tr class="bg-stone-50 font-semibold text-stone-900"><td colspan="5" class="px-3 py-2.5">Total</td><td class="num px-3">{{ money($totals['taxable']) }}</td><td class="num px-3">{{ money($totals['cgst']) }}</td><td class="num px-3">{{ money($totals['sgst']) }}</td><td class="num px-3">{{ money($totals['igst']) }}</td><td class="num px-3">{{ money($totals['tax']) }}</td><td class="num px-3">{{ money($totals['total']) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
    </section>
    <p class="mt-3 text-xs text-stone-500">This covers the GST you charged on sales. GST you paid on purchases (input credit) is not tracked here. Amounts are shown in your default currency and assume invoices were raised in it.</p>
</div>
