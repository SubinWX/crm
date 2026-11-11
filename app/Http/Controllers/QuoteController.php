<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\QuoteFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Quote PDFs and reference attachments. Routes are limited to sales.manage in routes/web.php. */
class QuoteController extends Controller
{
    /**
     * The current version. A draft is rendered live so staff can preview edits; an issued
     * quote always returns the PDF that was stored when it was marked as sent.
     */
    public function pdf(Request $request, Quote $quote)
    {
        $name = $quote->number.($quote->revision > 1 ? '-rev'.$quote->revision : '').($quote->status === 'draft' ? '-draft' : '').'.pdf';
        $issued = $quote->status === 'draft' ? null : $quote->revisions()->where('revision', $quote->revision)->first();

        $bytes = $issued && Storage::disk('local')->exists($issued->pdf_path)
            ? Storage::disk('local')->get($issued->pdf_path)
            : Quote::renderPdf($quote->toDocument());

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('inline') ? 'inline' : 'attachment').'; filename="'.$name.'"',
        ]);
    }

    /** A previously issued revision, exactly as it was sent. */
    public function revision(Quote $quote, int $revision)
    {
        $issued = $quote->revisions()->where('revision', $revision)->firstOrFail();
        abort_unless(Storage::disk('local')->exists($issued->pdf_path), 404);

        return Storage::disk('local')->download($issued->pdf_path, "{$quote->number}-rev{$revision}.pdf");
    }

    public function file(QuoteFile $file)
    {
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->name);
    }
}
