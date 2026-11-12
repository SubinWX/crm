<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lookup;
use App\Models\Payment;
use App\Models\User;
use App\Support\ExpenseFilters;
use App\Support\InvoiceFilters;
use App\Support\PaymentFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/**
 * PDF list reports for the finance screens. Each one honours the same filters as
 * the screen it is launched from; with no filters it contains every record.
 * Routes are restricted to Super Admins in routes/web.php.
 */
class ReportController extends Controller
{
    public function invoices(Request $request)
    {
        $filters = $request->only(['search', 'client', 'status', 'from', 'to']);
        $invoices = InvoiceFilters::apply(Invoice::query()->with('client'), $filters)
            ->orderBy('invoice_date')->orderBy('id')->get();

        $issued = $invoices->whereIn('status', Invoice::ISSUED_STATUSES);

        return $this->pdf('Invoices', $filters, 'invoice_date',
            ['Number', 'Client', 'Invoice date', 'Sent', 'Due', 'Status', ['Total', 'num'], ['Paid', 'num'], ['Balance', 'num']],
            $invoices->map(fn (Invoice $i) => [
                $i->number,
                $i->client->name,
                fmt_date($i->invoice_date),
                $i->sent_at ? fmt_date($i->sent_at->timezone(crm_tz())) : '—',
                fmt_date($i->due_date),
                $i->display_status_label,
                money($i->total, $i->currency),
                money($i->amount_paid, $i->currency),
                in_array($i->status, ['draft', 'void'], true) ? '—' : money($i->balance, $i->currency),
            ]),
            [
                'Invoices' => $invoices->count(),
                'Issued total' => money($issued->sum('total')),
                'Paid' => money($issued->sum('amount_paid')),
                'Outstanding' => money($issued->sum('total') - $issued->sum('amount_paid')),
            ],
            $invoices->pluck('currency')->unique()->count() > 1 ? 'Totals add invoices in different currencies at face value.' : null
        );
    }

    public function payments(Request $request)
    {
        $filters = $request->only(['search', 'client', 'method', 'receiver', 'from', 'to', 'unallocated']);
        $payments = PaymentFilters::apply(Payment::query()->with(['client', 'method', 'receiver', 'allocations.invoice']), $filters)
            ->orderBy('payment_date')->orderBy('id')->get();

        return $this->pdf('Payments', $filters, 'payment_date',
            ['Date', 'Client', 'Payment method', 'Payment To', 'Reference', 'Applied to', ['Amount', 'num'], ['Unallocated', 'num']],
            $payments->map(fn (Payment $p) => [
                fmt_date($p->payment_date),
                $p->client->name,
                $p->method?->name ?? '—',
                $p->receiver?->name ?? '—',
                $p->reference ?: '—',
                $p->allocations->map(fn ($a) => $a->invoice?->number)->filter()->implode(', ') ?: '—',
                money($p->amount),
                $p->unallocated > 0 ? money($p->unallocated) : '—',
            ]),
            [
                'Payments' => $payments->count(),
                'Received' => money($payments->sum('amount')),
                'Unallocated' => money($payments->sum(fn (Payment $p) => $p->unallocated)),
            ]
        );
    }

    public function expenses(Request $request)
    {
        $filters = $request->only(['search', 'category', 'client', 'method', 'from', 'to', 'recurring']);
        $expenses = ExpenseFilters::apply(Expense::query()->with(['category', 'method', 'client', 'link']), $filters)
            ->orderBy('expense_date')->orderBy('id')->get();

        return $this->pdf(! empty($filters['recurring']) ? 'Recurring expenses' : 'Expenses', $filters, 'expense_date',
            ['Date', 'Payee / vendor', 'Category', 'Linked to', 'Method', 'Recurring', 'Description', ['Amount', 'num']],
            $expenses->map(fn (Expense $e) => [
                fmt_date($e->expense_date),
                $e->payee ?: '—',
                $e->category?->name ?? '—',
                $e->linkedToLabel(),
                $e->method?->name ?? '—',
                $e->is_recurring ? (Expense::FREQUENCIES[$e->recurring_frequency] ?? 'Yes') : '—',
                str($e->description)->limit(90)->toString(),
                money($e->amount),
            ]),
            ['Expenses' => $expenses->count(), 'Total' => money($expenses->sum('amount'))]
        );
    }

    /**
     * @param  array<int, string|array{0:string,1:string}>  $columns
     * @param  iterable<int, array<int, string>>  $rows
     * @param  array<string, string|int>  $totals
     */
    protected function pdf(string $title, array $filters, string $dateLabel, array $columns, iterable $rows, array $totals, ?string $note = null)
    {
        activity('exported', "{$title} exported to PDF");

        return Pdf::loadView('pdf.report', [
            'title' => $title,
            'columns' => array_map(fn ($c) => is_array($c) ? $c : [$c, ''], $columns),
            'rows' => $rows,
            'totals' => $totals,
            'note' => $note,
            'criteria' => $this->describe($filters),
        ])->setPaper('a4', 'landscape')
            ->setOption(['isFontSubsettingEnabled' => true, 'isRemoteEnabled' => false])
            ->download(str($title)->slug().'-'.today_local()->format('Y-m-d').'.pdf');
    }

    /** Human-readable summary of the filters, printed under the report title. */
    protected function describe(array $filters): string
    {
        $parts = [];
        $f = fn (string $key) => $filters[$key] ?? null;

        if ($f('from') || $f('to')) {
            $parts[] = ($f('from') ? fmt_date($f('from')) : 'Beginning').' to '.($f('to') ? fmt_date($f('to')) : 'today');
        }
        if ($f('client')) {
            $parts[] = match (true) {
                $f('client') === 'overhead' => 'General overhead only',
                str_starts_with((string) $f('client'), 'link:') => 'Linked to: '.(Lookup::whereKey((int) substr((string) $f('client'), 5))->value('name') ?? '?'),
                default => 'Client: '.(Client::whereKey($f('client'))->value('name') ?? '?'),
            };
        }
        if ($f('status')) {
            $parts[] = 'Status: '.(Invoice::STATUSES[$f('status')] ?? $f('status'));
        }
        foreach (['method' => 'Method', 'category' => 'Category'] as $key => $label) {
            if ($f($key)) {
                $parts[] = $label.': '.(Lookup::whereKey($f($key))->value('name') ?? '?');
            }
        }
        if ($f('receiver')) {
            $parts[] = 'Payment To: '.(User::whereKey($f('receiver'))->value('name') ?? '?');
        }
        if (filter_var($f('unallocated'), FILTER_VALIDATE_BOOLEAN)) {
            $parts[] = 'Unallocated only';
        }
        if ($f('search')) {
            $parts[] = 'Search: "'.$f('search').'"';
        }

        return $parts ? implode(' · ', $parts) : 'All records';
    }
}
