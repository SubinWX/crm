<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 34px 34px 46px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1c1917; line-height: 1.4; }
        h1 { font-size: 18px; margin: 0; color: {{ \App\Support\Branding::shade(700) }}; }
        .muted { color: #78716c; }
        table { width: 100%; border-collapse: collapse; }
        .data th { background: #f5f5f4; text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: .4px; padding: 6px; border-bottom: 1px solid #d6d3d1; }
        .data td { padding: 6px; border-bottom: 1px solid #e7e5e4; vertical-align: top; }
        .data tr { page-break-inside: avoid; }
        .num, .data th.num { text-align: right; white-space: nowrap; }
        .totals td { padding: 8px 14px 8px 0; }
        .totals .label { font-size: 8px; text-transform: uppercase; letter-spacing: .4px; color: #78716c; }
        .totals .value { font-size: 13px; font-weight: bold; }
        .footer { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 8px; color: #78716c; }
    </style>
</head>
<body>
    <div class="footer">{{ setting('company_name', config('app.name')) }} · {{ $title }} · Generated {{ fmt_datetime(now()) }} ({{ crm_tz() }})</div>

    <table>
        <tr>
            <td style="vertical-align: top;">
                <h1>{{ $title }}</h1>
                <div class="muted" style="margin-top: 3px;">{{ $criteria }}</div>
            </td>
            <td style="vertical-align: top; text-align: right;">
                @if ($logo = \App\Support\Branding::pdfLogo(120, 30)) <img src="{{ $logo['src'] }}" alt="" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" style="width: {{ $logo['width'] }}px; height: {{ $logo['height'] }}px; margin-bottom: 4px;"><br> @endif
                <div style="font-size: 12px; font-weight: bold;">{{ setting('company_name', config('app.name')) }}</div>
                <div class="muted">{{ fmt_date(today_local()) }}</div>
            </td>
        </tr>
    </table>

    <table class="totals" style="margin: 12px 0 10px; width: auto;">
        <tr>
            @foreach ($totals as $label => $value)
                <td><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></td>
            @endforeach
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>@foreach ($columns as [$label, $class]) <th class="{{ $class }}">{{ $label }}</th> @endforeach</tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $i => $cell) <td class="{{ $columns[$i][1] }}">{{ $cell }}</td> @endforeach</tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="muted" style="text-align: center; padding: 24px;">No records match.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($note) <p class="muted" style="margin-top: 8px;">{{ $note }}</p> @endif
</body>
</html>
