@php
    // $doc is plain data (Quote::toDocument()), so an issued revision renders identically forever.
    $c = $doc['currency'];
    $frequencies = \App\Models\Quote::FREQUENCIES;
    $suffix = \App\Models\Quote::FREQUENCY_SUFFIX;
    $linkify = fn ($text) => preg_replace('~(https?://[^\s<]+)~i', '<a href="$1" style="color:'.\App\Support\Branding::shade(700).';">$1</a>', nl2br(e((string) $text)));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $doc['number'] }} — {{ $doc['title'] }}</title>
    <style>
        @page { margin: 40px 42px 54px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #1c1917; line-height: 1.5; }
        h1 { font-size: 22px; margin: 0; color: {{ \App\Support\Branding::shade(700) }}; letter-spacing: .5px; }
        h2 { font-size: 12.5px; margin: 18px 0 5px; color: {{ \App\Support\Branding::shade(700) }}; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #78716c; }
        .label { font-size: 8.5px; text-transform: uppercase; letter-spacing: .6px; color: #78716c; }
        .items th { background: #f5f5f4; text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: .5px; padding: 7px 8px; border-bottom: 1px solid #d6d3d1; }
        .items td { padding: 8px; border-bottom: 1px solid #e7e5e4; vertical-align: top; }
        .num, .items th.num { text-align: right; white-space: nowrap; }
        .totals td { padding: 3px 8px; }
        .totals .grand td { border-top: 1.5px solid #1c1917; font-size: 12px; font-weight: bold; padding-top: 6px; }
        .box { border: 1px solid #e7e5e4; padding: 8px 4px; margin-top: 10px; }
        .footer { position: fixed; bottom: -30px; left: 0; right: 0; text-align: center; font-size: 8.5px; color: #78716c; }
        .section { page-break-inside: auto; }
    </style>
</head>
<body>
    <div class="footer">{{ $doc['company']['name'] }} · {{ $doc['number'] }}{{ $doc['revision'] > 1 ? ' · Revision '.$doc['revision'] : '' }}</div>

    <table>
        <tr>
            <td style="vertical-align: top; width: 55%;">
                @if ($logo = \App\Support\Branding::pdfLogo()) <img src="{{ $logo['src'] }}" alt="" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" style="width: {{ $logo['width'] }}px; height: {{ $logo['height'] }}px; margin-bottom: 8px;"><br> @endif
                <div style="font-size: 15px; font-weight: bold;">{{ $doc['company']['name'] }}</div>
                <div class="muted">{!! nl2br(e($doc['company']['address'])) !!}</div>
                <div class="muted">{{ collect([$doc['company']['email'], $doc['company']['phone']])->filter()->implode(' · ') }}</div>
                @if ($doc['company']['tax_id']) <div class="muted">GSTIN / Tax ID: {{ $doc['company']['tax_id'] }}</div> @endif
            </td>
            <td style="vertical-align: top; text-align: right;">
                <h1>PROPOSAL</h1>
                <div style="font-size: 12px; font-weight: bold; margin-top: 4px;">{{ $doc['number'] }}{{ $doc['revision'] > 1 ? ' · Rev '.$doc['revision'] : '' }}</div>
            </td>
        </tr>
    </table>

    <div style="font-size: 16px; font-weight: bold; margin-top: 22px;">{{ $doc['title'] }}</div>

    <table style="margin-top: 12px;">
        <tr>
            <td style="vertical-align: top; width: 55%;">
                <div class="label">Prepared for</div>
                @if ($clientLogo = \App\Support\Branding::pdfImage($doc['client_logo_path'] ?? null, 110, 36)) <img src="{{ $clientLogo['src'] }}" alt="" width="{{ $clientLogo['width'] }}" height="{{ $clientLogo['height'] }}" style="width: {{ $clientLogo['width'] }}px; height: {{ $clientLogo['height'] }}px; margin: 4px 0 5px;"><br> @endif
                <div style="font-size: 12px; font-weight: bold;">{{ $doc['recipient']['company'] ?: $doc['recipient']['name'] }}</div>
                @if ($doc['recipient']['company']) <div>{{ $doc['recipient']['name'] }}</div> @endif
                <div class="muted">{{ $doc['recipient']['email'] }}</div>
                <div class="muted">{!! nl2br(e($doc['recipient']['address'])) !!}</div>
            </td>
            <td style="vertical-align: top;">
                <table>
                    <tr><td class="muted num">Issue date</td><td class="num" style="width: 110px;">{{ fmt_date($doc['issue_date']) }}</td></tr>
                    <tr><td class="muted num">Valid until</td><td class="num">{{ fmt_date($doc['expiry_date']) }}</td></tr>
                    <tr><td class="muted num">Currency</td><td class="num">{{ $c }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @foreach ($doc['sections'] as $section)
        <div class="section">
            <h2>{{ $section['title'] }}</h2>
            <div>{!! $linkify($section['body']) !!}</div>
        </div>
    @endforeach

    @if ($doc['items'])
        <h2>Pricing</h2>
        <table class="items">
            <thead><tr><th style="width: 46%;">Service / product</th><th class="num">Qty</th><th class="num">Unit price</th><th>Billing</th><th class="num">Amount</th></tr></thead>
            <tbody>
                @foreach ($doc['items'] as $item)
                    <tr>
                        <td><strong>{{ $item['name'] }}</strong>@if ($item['description'])<br><span class="muted">{{ $item['description'] }}</span>@endif</td>
                        <td class="num">{{ rtrim(rtrim(number_format($item['quantity'], 2), '0'), '.') }}</td>
                        <td class="num">{{ money($item['unit_price'], $c) }}</td>
                        <td>{{ $frequencies[$item['billing_frequency']] ?? $item['billing_frequency'] }}</td>
                        <td class="num">{{ money($item['amount'], $c) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table style="margin-top: 4px;">
            <tr>
                @foreach ($doc['totals'] as $frequency => $t)
                    <td style="vertical-align: top; width: {{ intdiv(100, max(count($doc['totals']), 2)) }}%; padding-right: 8px;">
                        <div class="box">
                            <table class="totals">
                                <tr><td colspan="2" class="label">{{ $frequency === 'one_time' ? 'One-time charges' : 'Recurring · '.$frequencies[$frequency] }}</td></tr>
                                <tr><td class="muted">Subtotal</td><td class="num">{{ money($t['subtotal'], $c) }}</td></tr>
                                @if ($t['discount'] > 0) <tr><td class="muted">Discount ({{ $doc['discount_percent'] }}%)</td><td class="num">- {{ money($t['discount'], $c) }}</td></tr> @endif
                                @if ($doc['tax_rate'] > 0) <tr><td class="muted">Tax ({{ $doc['tax_rate'] }}%)</td><td class="num">{{ money($t['tax'], $c) }}</td></tr> @endif
                                <tr class="grand"><td>Total</td><td class="num">{{ money($t['total'], $c) }}{{ $suffix[$frequency] }}</td></tr>
                            </table>
                        </div>
                    </td>
                @endforeach
                @if (count($doc['totals']) < 2) <td></td> @endif
            </tr>
        </table>
    @endif
</body>
</html>
