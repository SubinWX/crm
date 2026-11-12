@php
    // $doc is plain data (Quote::toDocument()), so an issued revision renders identically forever.
    $c = $doc['currency'];
    $frequencies = \App\Models\Quote::FREQUENCIES;
    $suffix = \App\Models\Quote::FREQUENCY_SUFFIX;
    // Editor text (bold, lists, links) cleaned for print; bare web addresses in older plain text become links.
    $linkify = fn ($text) => \App\Support\RichText::render((string) $text, autolink: true, linkColor: \App\Support\Branding::shade(700));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $doc['number'] }} — {{ $doc['title'] }}</title>
    @include('pdf._styles')
    <style>
        .box { margin-top: 10px; }
        .section { page-break-inside: auto; }
    </style>
</head>
<body>
    {{-- Logo, company details, footer and border come from Administration → PDF Settings. --}}
    @include('pdf._frame', ['reference' => $doc['number'].($doc['revision'] > 1 ? ' · Revision '.$doc['revision'] : '')])

    <table>
        <tr>
            <td style="vertical-align: bottom;"><h1 class="doc-title">PROPOSAL</h1></td>
            <td style="vertical-align: bottom; text-align: right;"><span class="doc-number">{{ $doc['number'] }}{{ $doc['revision'] > 1 ? ' · Rev '.$doc['revision'] : '' }}</span></td>
        </tr>
    </table>

    <div style="font-size: 15px; font-weight: bold; margin-top: 10px;">{{ $doc['title'] }}</div>


    <table style="margin-top: 12px;">
        <tr>
            <td style="vertical-align: top; width: 56%; padding-right: 12px;">
                <div class="panel">
                    <div class="panel-head">Prepared for</div>
                    <div class="panel-body">
                        @if ($clientLogo = \App\Support\Branding::pdfImage($doc['client_logo_path'] ?? null, 110, 34)) <img src="{{ $clientLogo['src'] }}" alt="" width="{{ $clientLogo['width'] }}" height="{{ $clientLogo['height'] }}" style="width: {{ $clientLogo['width'] }}px; height: {{ $clientLogo['height'] }}px; margin-bottom: 5px;"><br> @endif
                        <div style="font-size: 12px; font-weight: bold;">{{ $doc['recipient']['company'] ?: $doc['recipient']['name'] }}</div>
                        @if ($doc['recipient']['company']) <div>{{ $doc['recipient']['name'] }}</div> @endif
                        <div class="muted">{{ $doc['recipient']['email'] }}</div>
                        <div class="muted">{!! nl2br(e($doc['recipient']['address'])) !!}</div>
                    </div>
                </div>
            </td>
            <td style="vertical-align: top;">
                <div class="panel">
                    <div class="panel-head">Proposal details</div>
                    <div class="panel-body">
                        <table class="kv">
                            <tr><td class="k">Number</td><td class="num"><strong>{{ $doc['number'] }}</strong></td></tr>
                            <tr><td class="k">Issue date</td><td class="num">{{ fmt_date($doc['issue_date']) }}</td></tr>
                            <tr><td class="k">Valid until</td><td class="num">{{ fmt_date($doc['expiry_date']) }}</td></tr>
                            <tr><td class="k">Currency</td><td class="num">{{ $c }}</td></tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    @foreach ($doc['sections'] as $section)
        <div class="section">
            <h2>{{ $section['title'] }}</h2>
            <div class="rich">{!! $linkify($section['body']) !!}</div>
        </div>
    @endforeach

    @if ($doc['items'])
        <h2>Pricing</h2>
        <table class="items">
            <thead><tr><th style="width: 22px;">#</th><th style="width: 44%;">Service / product</th><th class="num">Qty</th><th class="num">Unit price</th><th>Billing</th><th class="num">Amount</th></tr></thead>
            <tbody>
                @foreach ($doc['items'] as $item)
                    <tr>
                        <td class="muted">{{ $loop->iteration }}</td>
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
                                <tr><td colspan="2" style="background: {{ \App\Support\PdfDesign::tint() }}; font-weight: bold; color: {{ \App\Support\PdfDesign::accent() }};" class="label">{{ $frequency === 'one_time' ? 'One-time charges' : 'Recurring · '.$frequencies[$frequency] }}</td></tr>
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
