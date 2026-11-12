{{--
    Border, header and footer drawn on every page of a PDF. Include once, straight after <body>.
    $reference is an optional short line for the footer (document number, report name).
--}}
@php
    $design = \App\Support\PdfDesign::class;
    $style = $design::get('header_style');
    $logo = $design::logo(170, 50);
    $lines = $design::headerLines();
    $footer = $design::footerLines();
    $reference ??= null;
@endphp
@if ($design::border()) <div class="pdf-border"></div> @endif

<div class="pdf-header">
    @if ($style === 'centered')
        <div class="centered">
            @if ($logo) <img src="{{ $logo['src'] }}" alt="" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" style="width: {{ round($logo['width'] * 0.72) }}px; height: {{ round($logo['height'] * 0.72) }}px;"><br>
            @else <span class="title">{{ $design::title() }}</span><br> @endif
            @if ($design::get('header_tagline') !== '') <span class="tagline">{{ $design::get('header_tagline') }}</span><br> @endif
            <span class="details">{{ implode('   |   ', array_slice($lines, 0, 3)) }}</span>
        </div>
    @else
        <table>
            <tr>
                <td style="width: 50%;">
                    @if ($logo)
                        <img src="{{ $logo['src'] }}" alt="" width="{{ $logo['width'] }}" height="{{ $logo['height'] }}" style="width: {{ $logo['width'] }}px; height: {{ $logo['height'] }}px;">
                        @if ($design::get('header_tagline') !== '') <div class="tagline" style="margin-top: 3px;">{{ $design::get('header_tagline') }}</div> @endif
                    @else
                        <div class="title">{{ $design::title() }}</div>
                        @if ($design::get('header_tagline') !== '') <div class="tagline">{{ $design::get('header_tagline') }}</div> @endif
                    @endif
                </td>
                <td style="text-align: right;">
                    @if ($logo) <div class="title" style="font-size: 12.5px;">{{ $design::title() }}</div> @endif
                    <div class="details">@foreach ($lines as $line){{ $line }}@if (! $loop->last)<br>@endif @endforeach</div>
                </td>
            </tr>
        </table>
    @endif
</div>

<div class="pdf-footer">
    <table>
        <tr>
            <td>@foreach ($footer as $line){{ $line }}@if (! $loop->last)<br>@endif @endforeach</td>
            <td style="text-align: right; width: 34%;">
                @if ($reference) {{ $reference }}<br> @endif
                @if ($design::pageNumbers()) <span class="page"></span> @endif
            </td>
        </tr>
    </table>
</div>
