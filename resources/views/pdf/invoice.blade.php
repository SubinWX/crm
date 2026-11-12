@php
    // DejaVu Sans is bundled with dompdf and has wide Unicode coverage (₹, €, £).
    $c = $invoice->currency;
    // A GST invoice is a "Tax Invoice" with GSTINs, place of supply, HSN/SAC codes and the CGST/SGST/IGST split.
    $gst = $invoice->gstKind() !== 'none';
    $billTo = $invoice->recipient_name ?: ($invoice->client->contacts->firstWhere('role', 'billing')?->name ?: $invoice->client->contact_name);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    @include('pdf._styles')
</head>
<body>
    {{-- Logo, company details, footer and border come from Administration → PDF Settings. --}}
    @include('pdf._frame', ['reference' => 'Invoice '.$invoice->number])

    <table>
        <tr>
            <td style="vertical-align: bottom;">
                <h1 class="doc-title">{{ $gst ? 'TAX INVOICE' : 'INVOICE' }}</h1>
            </td>
            <td style="vertical-align: bottom; text-align: right;">
                <span class="doc-number">{{ $invoice->number }}</span>
                @if (in_array($invoice->status, ['draft', 'paid', 'void'])) &nbsp; <span class="status">{{ $invoice->display_status_label }}</span> @endif
            </td>
        </tr>
    </table>

    <table style="margin-top: 14px;">
        <tr>
            <td style="vertical-align: top; width: 56%; padding-right: 12px;">
                <div class="panel">
                    <div class="panel-head">Bill to</div>
                    <div class="panel-body">
                        @if ($clientLogo = \App\Support\Branding::pdfImage($invoice->client->logo_path, 110, 34)) <img src="{{ $clientLogo['src'] }}" alt="" width="{{ $clientLogo['width'] }}" height="{{ $clientLogo['height'] }}" style="width: {{ $clientLogo['width'] }}px; height: {{ $clientLogo['height'] }}px; margin-bottom: 5px;"><br> @endif
                        <div style="font-size: 12.5px; font-weight: bold;">{{ $invoice->client->name }}</div>
                        @if ($billTo) <div>{{ $billTo }}</div> @endif
                        <div class="muted">{!! nl2br(e($invoice->client->billing_address)) !!}</div>
                        @if ($gst)
                            <div class="muted">GSTIN: {{ $invoice->client_gstin ?: 'Unregistered' }}</div>
                            <div class="muted">Place of supply: {{ \App\Support\Gst::stateName($invoice->place_of_supply) }}</div>
                        @elseif ($invoice->client->tax_id) <div class="muted">GSTIN / Tax ID: {{ $invoice->client->tax_id }}</div> @endif
                    </div>
                </div>
            </td>
            <td style="vertical-align: top;">
                <div class="panel">
                    <div class="panel-head">Invoice details</div>
                    <div class="panel-body">
                        <table class="kv">
                            <tr><td class="k">Invoice number</td><td class="num"><strong>{{ $invoice->number }}</strong></td></tr>
                            <tr><td class="k">Invoice date</td><td class="num">{{ fmt_date($invoice->invoice_date) }}</td></tr>
                            <tr><td class="k">Due date</td><td class="num">{{ fmt_date($invoice->due_date) }}</td></tr>
                            <tr><td class="k">Currency</td><td class="num">{{ $c }}</td></tr>
                            @if ($gst) <tr><td class="k">Supplier state</td><td class="num">{{ \App\Support\Gst::stateName($invoice->supplier_state) }}</td></tr> @endif
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 16px;">
        <thead><tr><th style="width: 22px;">#</th><th style="width: {{ $gst ? 40 : 50 }}%;">Description</th>@if ($gst)<th>HSN / SAC</th>@endif<th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td class="muted">{{ $loop->iteration }}</td>
                    <td>{{ $item->description }}</td>
                    @if ($gst) <td>{{ $item->hsn_sac ?: '—' }}</td> @endif
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                    <td class="num">{{ money($item->unit_price, $c) }}</td>
                    <td class="num">{{ money($item->amount, $c) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 14px; page-break-inside: avoid;">
        <tr>
            <td style="vertical-align: top; width: 56%; padding-right: 12px;">
                @if ($gst)
                    <div class="label">Amount in words</div>
                    <div style="margin: 2px 0 10px; font-weight: bold;">{{ \App\Support\Gst::amountInWords((float) $invoice->total, $c) }}</div>
                @endif
                @if ($invoice->notes)
                    <div class="label">Notes</div>
                    <div class="rich" style="margin-top: 2px;">{!! \App\Support\RichText::render($invoice->notes) !!}</div>
                @endif
            </td>
            <td style="vertical-align: top;">
                <table class="totals">
                    <tr><td class="muted">Subtotal</td><td class="num">{{ money($invoice->subtotal, $c) }}</td></tr>
                    @if ((float) $invoice->discount > 0) <tr><td class="muted">Discount</td><td class="num">- {{ money($invoice->discount, $c) }}</td></tr> @endif
                    @if ($gst) <tr><td class="muted">Taxable value</td><td class="num">{{ money($invoice->taxable_value, $c) }}</td></tr> @endif
                    @foreach ($invoice->taxLines() as $label => $amount)
                        <tr><td class="muted">{{ $label }}</td><td class="num">{{ money($amount, $c) }}</td></tr>
                    @endforeach
                    <tr class="grand"><td>Total</td><td class="num">{{ money($invoice->total, $c) }}</td></tr>
                    @if ((float) $invoice->amount_paid > 0)
                        <tr><td class="muted">Paid</td><td class="num">{{ money($invoice->amount_paid, $c) }}</td></tr>
                        <tr class="strong"><td>Balance due</td><td class="num">{{ money($invoice->balance, $c) }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
