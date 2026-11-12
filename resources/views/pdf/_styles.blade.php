{{--
    Styles shared by every PDF. Header and footer are "fixed", which makes the PDF engine repeat
    them on each page; the page margins leave room for them. Colours, borders and the table style
    come from Administration → PDF Settings.
    $side is the left/right page margin and $font the base font size (reports are smaller and wider).
--}}
@php
    $design = \App\Support\PdfDesign::class;
    $accent = $design::accent();
    $onAccent = $design::onAccent();
    $tint = $design::tint();
    $side ??= 44;
    $font ??= 10.5;
    $top = $design::HEADER_HEIGHT + 34;
    $bottom = $design::FOOTER_HEIGHT + 34;
    $grid = $design::get('table_style') === 'grid';
    $band = $design::get('header_style') === 'band';
    // The border sits 16pt in from the paper edge; header and footer live inside it.
    $edge = $side - 16;
@endphp
<style>
    @page { margin: {{ $top }}px {{ $side }}px {{ $bottom }}px {{ $side }}px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: {{ $font }}px; color: #1c1917; line-height: 1.45; }
    table { width: 100%; border-collapse: collapse; }
    .muted { color: #6b635d; }
    .num { text-align: right; white-space: nowrap; }
    .label { font-size: {{ $font - 2 }}px; text-transform: uppercase; letter-spacing: .6px; color: #6b635d; }

    /* Frame: border, header and footer repeated on every page. */
    .pdf-border { position: fixed; top: -{{ $top - 16 }}px; left: -{{ $edge }}px; right: -{{ $edge }}px; bottom: -{{ $bottom - 16 }}px; border: 1.2px solid {{ $accent }}; }
    .pdf-header { position: fixed; top: -{{ $top - 16 }}px; left: -{{ $edge }}px; right: -{{ $edge }}px; height: {{ $design::HEADER_HEIGHT - 10 }}px; padding: 10px {{ $edge }}px 0; {{ $band ? "background: {$accent}; color: {$onAccent};" : "border-bottom: 2px solid {$accent};" }} }
    .pdf-header table { height: {{ $design::HEADER_HEIGHT - 22 }}px; }
    .pdf-header td { vertical-align: middle; }
    .pdf-header .centered { padding-top: 4px; text-align: center; }
    .pdf-header .title { font-size: 15px; font-weight: bold; color: {{ $band ? $onAccent : '#1c1917' }}; }
    .pdf-header .tagline { font-size: 8.5px; text-transform: uppercase; letter-spacing: 1px; color: {{ $band ? $onAccent : $accent }}; }
    .pdf-header .details { font-size: 8.5px; line-height: 1.5; color: {{ $band ? $onAccent : '#6b635d' }}; }
    .pdf-footer { position: fixed; bottom: -{{ $bottom - 16 }}px; left: -{{ $edge }}px; right: -{{ $edge }}px; height: {{ $design::FOOTER_HEIGHT }}px; padding: 7px {{ $edge }}px 0; border-top: 1px solid {{ $accent }}; background: {{ $tint }}; font-size: 8.5px; line-height: 1.5; color: #57534e; }
    .pdf-footer td { vertical-align: top; }
    .pdf-footer .page:after { content: "Page " counter(page); }

    /* Document title block at the top of the first page. */
    .doc-title { font-size: 21px; font-weight: bold; letter-spacing: 1px; color: {{ $accent }}; margin: 0; }
    .doc-number { font-size: 12.5px; font-weight: bold; }
    .status { display: inline-block; padding: 2px 9px; border: 1px solid {{ $accent }}; color: {{ $accent }}; font-size: 8.5px; text-transform: uppercase; letter-spacing: .6px; }
    h2 { font-size: 12.5px; margin: 18px 0 6px; padding-bottom: 3px; color: {{ $accent }}; border-bottom: 1px solid {{ $design::tint(0.7) }}; }

    /* Bordered panels (Bill to, details). */
    .panel { border: 1px solid #d6d3d1; }
    .panel .panel-head { background: {{ $tint }}; border-bottom: 1px solid #d6d3d1; padding: 4px 9px; font-size: {{ $font - 2 }}px; font-weight: bold; text-transform: uppercase; letter-spacing: .6px; color: {{ $accent }}; }
    .panel .panel-body { padding: 8px 9px; }
    .kv td { padding: 2.5px 0; }
    .kv td.k { color: #6b635d; }

    /* Line-item and report tables. */
    .items { {{ $grid ? 'border: 1px solid #a8a29e;' : '' }} }
    .items th { background: {{ $accent }}; color: {{ $onAccent }}; text-align: left; font-size: {{ $font - 2 }}px; text-transform: uppercase; letter-spacing: .5px; padding: 6px 8px; {{ $grid ? "border: 1px solid {$accent};" : '' }} }
    .items th.num { text-align: right; }
    .items td { padding: 7px 8px; vertical-align: top; {{ $grid ? 'border: 1px solid #d6d3d1;' : 'border-bottom: 1px solid #e7e5e4;' }} }
    .items tr { page-break-inside: avoid; }

    /* Totals box. */
    .totals { border: 1px solid #d6d3d1; }
    .totals td { padding: 4.5px 9px; border-bottom: 1px solid #ebe9e7; }
    .totals .grand td { background: {{ $accent }}; color: {{ $onAccent }}; font-size: {{ $font + 2 }}px; font-weight: bold; padding: 7px 9px; border-bottom: 0; }
    .totals .strong td { font-weight: bold; border-bottom: 0; }

    /* Text written with the editor. */
    .rich ul, .rich ol { margin: 4px 0; padding-left: 18px; }
    .rich li { margin: 1px 0; }
    .rich h1, .rich h2, .rich h3 { font-size: 1.05em; margin: 6px 0 2px; padding: 0; border: 0; color: #1c1917; }
    .rich blockquote { margin: 4px 0; padding-left: 10px; border-left: 2px solid #d6d3d1; }
    .rich div, .rich p { margin: 0; }
</style>
