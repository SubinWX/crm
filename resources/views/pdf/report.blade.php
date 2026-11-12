<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    @include('pdf._styles', ['side' => 38, 'font' => 9.5])
    <style>
        .summary { border: 1px solid #d6d3d1; margin: 12px 0 12px; }
        .summary td { padding: 7px 12px; border-right: 1px solid #e7e5e4; }
        .summary .value { font-size: 13px; font-weight: bold; }
    </style>
</head>
<body>
    {{-- Logo, company details, footer and border come from Administration → PDF Settings. --}}
    @include('pdf._frame', ['reference' => $title.' · Generated '.fmt_datetime(now())])

    <table>
        <tr>
            <td style="vertical-align: bottom;">
                <h1 class="doc-title" style="font-size: 18px; letter-spacing: .3px;">{{ $title }}</h1>
                <div class="muted" style="margin-top: 3px;">{{ $criteria }}</div>
            </td>
            <td style="vertical-align: bottom; text-align: right;" class="muted">{{ fmt_date(today_local()) }} · {{ crm_tz() }}</td>
        </tr>
    </table>

    <table class="summary" style="width: auto;">
        <tr>
            @foreach ($totals as $label => $value)
                <td><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></td>
            @endforeach
        </tr>
    </table>

    <table class="items">
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
