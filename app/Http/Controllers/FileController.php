<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\ClientFile;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JobApplication;
use App\Models\Poster;
use App\Models\User;
use App\Support\ExpenseFilters;
use App\Support\InvoicePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Every stored file lives on the private "local" disk and is only reachable through
 * these actions, which sit behind the auth + permission middleware in routes/web.php.
 */
class FileController extends Controller
{
    public function clientFile(ClientFile $clientFile)
    {
        Gate::authorize('client.access', $clientFile->client);

        return $this->download($clientFile->path, $clientFile->name);
    }

    public function clientLogo(Client $client)
    {
        abort_unless($client->logo_path, 404);

        return $this->inline($client->logo_path, Storage::disk('local')->mimeType($client->logo_path) ?: 'image/png');
    }

    public function userAvatar(User $user)
    {
        abort_unless($user->avatar_path, 404);

        return $this->inline($user->avatar_path, Storage::disk('local')->mimeType($user->avatar_path) ?: 'image/jpeg');
    }

    public function invoicePdf(Request $request, Invoice $invoice)
    {
        $pdf = InvoicePdf::make($invoice);
        $name = $invoice->number.'.pdf';

        return $request->boolean('inline') ? $pdf->stream($name) : $pdf->download($name);
    }

    public function invoiceUpload(Invoice $invoice)
    {
        abort_unless($invoice->uploaded_pdf_path, 404);

        return $this->download($invoice->uploaded_pdf_path, $invoice->uploaded_pdf_name ?: $invoice->number.'-sent-copy.pdf');
    }

    public function expenseReceipt(Expense $expense)
    {
        abort_unless($expense->receipt_path, 404);

        return $this->download($expense->receipt_path, $expense->receipt_name ?: 'receipt');
    }

    public function posterImage(Poster $poster)
    {
        return $this->inline($poster->image_path, $poster->mime);
    }

    public function posterThumb(Poster $poster)
    {
        $path = $poster->thumb_path && Storage::disk('local')->exists($poster->thumb_path) ? $poster->thumb_path : $poster->image_path;

        return $this->inline($path, $path === $poster->image_path ? $poster->mime : 'image/jpeg');
    }

    public function posterDownload(Poster $poster)
    {
        return $this->download($poster->image_path, $poster->original_name);
    }

    public function expensesCsv(Request $request)
    {
        $query = ExpenseFilters::apply(
            Expense::query()->with(['category', 'method', 'client', 'link']),
            $request->only(['search', 'category', 'client', 'method', 'from', 'to', 'recurring'])
        )->orderBy('expense_date')->orderBy('id');

        activity('exported', 'Expenses exported to CSV');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM so Excel reads UTF-8
            fputcsv($out, ['Date', 'Amount', 'Currency', 'Category', 'Payee / Vendor', 'Payment method', 'Client', 'Recurring', 'Description']);
            $query->chunk(500, function ($expenses) use ($out) {
                foreach ($expenses as $e) {
                    fputcsv($out, [
                        $e->expense_date->format('Y-m-d'),
                        number_format((float) $e->amount, 2, '.', ''),
                        setting('currency', 'INR'),
                        $e->category?->name,
                        self::csvSafe($e->payee),
                        $e->method?->name,
                        self::csvSafe($e->linkedToLabel()),
                        $e->is_recurring ? ($e->recurring_frequency ?: 'yes') : 'no',
                        self::csvSafe($e->description),
                    ]);
                }
            });
            fclose($out);
        }, 'expenses-'.today_local()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** A job applicant's resume. The route is limited to people who manage careers. */
    public function resume(JobApplication $application)
    {
        $extension = strtolower(pathinfo($application->resume_name, PATHINFO_EXTENSION)) ?: 'pdf';

        return $this->download($application->resume_path, str($application->name)->slug().'-resume.'.$extension);
    }

    /**
     * Shows a PDF resume in the browser tab. Anything that is not really a PDF (Word files,
     * or a file only named .pdf) is downloaded instead, so the browser never renders it.
     */
    public function resumeView(JobApplication $application)
    {
        $path = $application->resume_path;
        abort_unless(Storage::disk('local')->exists($path), 404);

        if (! $application->resumeIsPdf() || Storage::disk('local')->mimeType($path) !== 'application/pdf') {
            return $this->resume($application);
        }

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.str($application->name)->slug().'-resume.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Stop spreadsheet apps treating user text as a formula. */
    protected static function csvSafe(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /** Office → Documents: the owner, whoever added it, their managers and Super Admins; everyone for a company document. */
    public function document(Request $request, Document $document)
    {
        abort_unless($document->canBeOpenedBy($request->user()), 403);

        return $this->download($document->path, $document->original_name);
    }

    protected function download(string $path, string $name)
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $name);
    }

    protected function inline(string $path, string $mime)
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
