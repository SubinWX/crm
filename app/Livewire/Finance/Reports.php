<?php

namespace App\Livewire\Finance;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Finance → Reports: money in and out by month, income by client, what clients still owe by age,
 * and how many leads turned into clients. Everything is worked out from the records already in the CRM.
 */
#[Title('Reports')]
class Reports extends Component
{
    public const PERIODS = ['fy' => 'This financial year', 'last_fy' => 'Last financial year', 'year' => 'This calendar year', '12m' => 'Last 12 months'];

    #[Url(except: 'fy')]
    public string $period = 'fy';

    public function booted(): void
    {
        Gate::authorize('finance');
    }

    /** @return array{0: Carbon, 1: Carbon} first and last day of the chosen period. The financial year runs April to March. */
    protected function range(): array
    {
        $today = today_local();
        $fyStart = $today->copy()->month(4)->startOfMonth();
        if ($today->lt($fyStart)) {
            $fyStart->subYear();
        }

        return match ($this->period) {
            'last_fy' => [$fyStart->copy()->subYear(), $fyStart->copy()->subDay()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
            '12m' => [$today->copy()->startOfMonth()->subMonthsNoOverflow(11), $today->copy()->endOfMonth()],
            default => [$fyStart, $fyStart->copy()->addYear()->subDay()],
        };
    }

    public function render()
    {
        if (! array_key_exists($this->period, self::PERIODS)) {
            $this->period = 'fy';
        }
        [$from, $to] = $this->range();
        [$start, $end] = [$from->toDateString(), $to->toDateString()];
        $today = today_local();

        // ---- Money in and out, month by month ----
        $byMonth = fn ($query, string $column, string $sum) => $query->whereBetween($column, [$start, $end])->toBase()
            ->selectRaw("date_format({$column}, '%Y-%m') as ym, sum({$sum}) as total")->groupBy('ym')->pluck('total', 'ym');
        $invoiced = $byMonth(Invoice::query()->whereIn('status', Invoice::ISSUED_STATUSES), 'invoice_date', 'subtotal - discount');
        $received = $byMonth(Payment::query(), 'payment_date', 'amount');
        $spent = $byMonth(Expense::query(), 'expense_date', 'amount');
        $months = collect();
        for ($m = $from->copy()->startOfMonth(); $m->lte($to); $m->addMonthNoOverflow()) {
            $key = $m->format('Y-m');
            $in = (float) ($received[$key] ?? 0);
            $out = (float) ($spent[$key] ?? 0);
            $months->push(['label' => $m->format('M Y'), 'future' => $m->gt($today), 'invoiced' => (float) ($invoiced[$key] ?? 0), 'received' => $in, 'spent' => $out, 'profit' => $in - $out]);
        }
        $totals = ['invoiced' => $months->sum('invoiced'), 'received' => $months->sum('received'), 'spent' => $months->sum('spent'), 'profit' => $months->sum('profit')];

        // ---- Income by client ----
        $clients = Payment::query()->whereBetween('payment_date', [$start, $end])->toBase()->join('clients', 'clients.id', '=', 'payments.client_id')
            ->selectRaw('clients.id, clients.name, sum(payments.amount) as received, count(*) as payments')->groupBy('clients.id', 'clients.name')->orderByDesc('received')->limit(10)->get();

        // ---- What is still owed, by how late it is (as of today, whatever the period) ----
        $buckets = ['Not due yet' => 0.0, '1 to 30 days late' => 0.0, '31 to 60 days late' => 0.0, '61 to 90 days late' => 0.0, 'Over 90 days late' => 0.0];
        $owing = [];
        foreach (Invoice::query()->open()->with('client')->get() as $invoice) {
            $balance = $invoice->balance;
            if ($balance <= 0) {
                continue;
            }
            $late = $invoice->due_date ? -1 * (int) days_until($invoice->due_date) : 0;
            $bucket = match (true) {
                $late <= 0 => 'Not due yet', $late <= 30 => '1 to 30 days late', $late <= 60 => '31 to 60 days late', $late <= 90 => '61 to 90 days late', default => 'Over 90 days late',
            };
            $buckets[$bucket] += $balance;
            $name = $invoice->client?->name ?? 'Unknown';
            $owing[$name] = ['name' => $name, 'id' => $invoice->client_id, 'balance' => ($owing[$name]['balance'] ?? 0) + $balance, 'late' => max($owing[$name]['late'] ?? 0, max(0, $late))];
        }
        $owing = collect($owing)->sortByDesc('balance')->take(8)->values();

        // ---- Leads ----
        $leads = Lead::query()->whereBetween('created_at', [$from->copy()->startOfDay()->utc(), $to->copy()->endOfDay()->utc()])->toBase()
            ->selectRaw('stage, count(*) as n')->groupBy('stage')->pluck('n', 'stage');
        $leadTotal = (int) $leads->sum();
        $won = (int) ($leads['won'] ?? 0);
        $lost = (int) ($leads['lost'] ?? 0);

        return view('livewire.finance.reports', [
            'from' => $from, 'to' => $to, 'months' => $months, 'totals' => $totals,
            'peak' => max(1.0, (float) $months->max(fn ($m) => max($m['received'], $m['spent']))),
            'clients' => $clients, 'buckets' => $buckets, 'owed' => array_sum($buckets), 'owing' => $owing,
            'leads' => ['total' => $leadTotal, 'won' => $won, 'lost' => $lost, 'open' => $leadTotal - $won - $lost,
                'rate' => $won + $lost > 0 ? round($won / ($won + $lost) * 100) : null,
                'stages' => collect(Lead::STAGES)->map(fn ($label, $key) => ['label' => $label, 'n' => (int) ($leads[$key] ?? 0)])],
        ]);
    }
}
