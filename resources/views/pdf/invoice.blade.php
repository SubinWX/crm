@php
    // DejaVu Sans is bundled with dompdf and has wide Unicode coverage (₹, €, £).
    $c = $invoice->currency;
    $company = setting('company_name', config('app.name'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    <style>
        @page { margin: 36px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1c1917; line-height: 1.45; }
        h1 { font-size: 24px; margin: 0; letter-spacing: 1px; color: {{ \App\Support\Branding::shade(700) }}; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #78716c; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: .6px; color: #78716c; margin-bottom: 2px; }
        .items th { background: #f5f5f4; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; padding: 7px 8px; border-bottom: 1px solid #d6d3d1; }
        .items td { padding: 8px; border-bottom: 1px solid #e7e5e4; vertical-align: top; }
        .num, .items th.num { text-align: right; white-space: nowrap; }
        .totals td { padding: 4px 8px; }
        .totals .grand td { border-top: 1.5px solid #1c1917; font-size: 13px; font-weight: bold; padding-top: 7px; }
        .status { display: inline-block; padding: 2px 8px; border: 1px solid {{ \App\Support\Branding::shade(700) }}; color: {{ \App\Support\Branding::shade(700) }}; font-size: 9px; text-transform: uppercase; letter-spacing: .6px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #78716c; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="vertical-align: top; width: 55%;">
                {{-- Company logo, scaled to fit a fixed box whatever its shape. --}}
                @if ($logo = \App\Support\Branding::pdfLogo()) <img src="{{ $logo['src'] }}" alt="" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" style="width: {{ $logo['width'] }}px; height: {{ $logo['height'] }}px; margin-bottom: 8px;"><br> @endif
                <div style="font-size: 16px; font-weight: bold;">{{ $company }}</div>
                <div class="muted">{!! nl2br(e(setting('company_address'))) !!}</div>
                <div class="muted">{{ collect([setting('company_email'), setting('company_phone')])->filter()->implode(' · ') }}</div>
                @if (setting('company_tax_id')) <div class="muted">GSTIN / Tax ID: {{ setting('company_tax_id') }}</div> @endif
            </td>
            <td style="vertical-align: top; text-align: right;">
                <h1>INVOICE</h1>
                <div style="font-size: 13px; font-weight: bold; margin-top: 4px;">{{ $invoice->number }}</div>
                @if (in_array($invoice->status, ["draft", "paid", "void"]))<div style="margin-top: 6px;"><span class="status">{{ $invoice->display_status_label }}</span></div>@endif
            </td>
        </tr>
    </table>

    <table style="margin-top: 26px;">
        <tr>
            <td style="vertical-align: top; width: 55%;">
                <div class="label">Bill to</div>
                @if ($clientLogo = \App\Support\Branding::pdfImage($invoice->client->logo_path, 110, 36)) <img src="{{ $clientLogo['src'] }}" alt="" width="{{ $clientLogo['width'] }}" height="{{ $clientLogo['height'] }}" style="width: {{ $clientLogo['width'] }}px; height: {{ $clientLogo['height'] }}px; margin: 4px 0 5px;"><br> @endif
                <div style="font-size: 13px; font-weight: bold;">{{ $invoice->client->name }}</div>
                @php $billTo = $invoice->recipient_name ?: ($invoice->client->contacts->firstWhere('role', 'billing')?->name ?: $invoice->client->contact_name); @endphp
                @if ($billTo) <div>{{ $billTo }}</div> @endif
                <div class="muted">{!! nl2br(e($invoice->client->billing_address)) !!}</div>
                @if ($invoice->client->tax_id) <div class="muted">GSTIN / Tax ID: {{ $invoice->client->tax_id }}</div> @endif
            </td>
            <td style="vertical-align: top;">
                <table>
                    <tr><td class="muted num">Invoice date</td><td class="num" style="width: 110px;">{{ fmt_date($invoice->invoice_date) }}</td></tr>
                    <tr><td class="muted num">Due date</td><td class="num">{{ fmt_date($invoice->due_date) }}</td></tr>
                    <tr><td class="muted num">Currency</td><td class="num">{{ $c }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top: 24px;">
        <thead><tr><th style="width: 52%;">Description</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                    <td class="num">{{ money($item->unit_price, $c) }}</td>
                    <td class="num">{{ money($item->amount, $c) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 14px;">
        <tr>
            <td style="vertical-align: top; width: 55%; padding-right: 24px;">
                @if ($invoice->notes)
                    <div class="label">Notes</div>
                    <div>{!! nl2br(e($invoice->notes)) !!}</div>
                @endif
            </td>
            <td style="vertical-align: top;">
                <table class="totals">
                    <tr><td class="muted">Subtotal</td><td class="num">{{ money($invoice->subtotal, $c) }}</td></tr>
                    @if ((float) $invoice->discount > 0) <tr><td class="muted">Discount</td><td class="num">- {{ money($invoice->discount, $c) }}</td></tr> @endif
                    <tr><td class="muted">Tax ({{ (float) $invoice->tax_rate }}%)</td><td class="num">{{ money($invoice->tax_amount, $c) }}</td></tr>
                    <tr class="grand"><td>Total</td><td class="num">{{ money($invoice->total, $c) }}</td></tr>
                    @if ((float) $invoice->amount_paid > 0)
                        <tr><td class="muted">Paid</td><td class="num">{{ money($invoice->amount_paid, $c) }}</td></tr>
                        <tr><td style="font-weight: bold;">Balance due</td><td class="num" style="font-weight: bold;">{{ money($invoice->balance, $c) }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">{{ setting('invoice_footer') }}</div>
</body>
</html>
