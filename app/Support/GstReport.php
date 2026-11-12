<?php

namespace App\Support;

use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The month's outward supplies for the accountant (the figures behind GSTR-1 and GSTR-3B):
 * every invoice that was issued (sent, part-paid or paid) with an invoice date in the month.
 * Drafts and voided invoices are left out. Used by the GST Summary screen and its CSV downloads.
 */
class GstReport
{
    public Collection $invoices;

    public Carbon $start;

    public function __construct(string $month)
    {
        $this->start = Carbon::createFromFormat('Y-m-d', $month.'-01', crm_tz())->startOfDay();
        $this->invoices = Invoice::query()->with(['client', 'items'])
            ->whereIn('status', Invoice::ISSUED_STATUSES)
            ->whereBetween('invoice_date', [$this->start->toDateString(), $this->start->copy()->endOfMonth()->toDateString()])
            ->orderBy('invoice_date')->orderBy('number')->get();
    }

    public static function validMonth(?string $month): bool
    {
        return (bool) preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', (string) $month);
    }

    /** @return array<string, float|int> */
    public function totals(?Collection $invoices = null): array
    {
        $invoices ??= $this->invoices;

        return [
            'count' => $invoices->count(),
            'taxable' => round($invoices->sum(fn (Invoice $i) => $i->taxable_value), 2),
            'cgst' => round($invoices->sum(fn ($i) => (float) $i->cgst_amount), 2),
            'sgst' => round($invoices->sum(fn ($i) => (float) $i->sgst_amount), 2),
            'igst' => round($invoices->sum(fn ($i) => (float) $i->igst_amount), 2),
            'tax' => round($invoices->sum(fn ($i) => (float) $i->tax_amount), 2),
            'total' => round($invoices->sum(fn ($i) => (float) $i->total), 2),
        ];
    }

    /** Registered clients (with a GSTIN) are B2B; everyone else is B2C. @return array{b2b: array, b2c: array} */
    public function byBuyerType(): array
    {
        return [
            'b2b' => $this->totals($this->invoices->filter(fn (Invoice $i) => filled($i->client_gstin))),
            'b2c' => $this->totals($this->invoices->filter(fn (Invoice $i) => blank($i->client_gstin))),
        ];
    }

    /** One row per GST rate. */
    public function byRate(): Collection
    {
        return $this->invoices->groupBy(fn (Invoice $i) => (string) (float) $i->tax_rate)
            ->map(fn (Collection $group, string $rate) => ['rate' => (float) $rate] + $this->totals($group))
            ->sortBy('rate')->values();
    }

    /**
     * One row per HSN/SAC code and rate. An invoice's discount is spread over its lines in
     * proportion to their amounts, and the last line takes any rounding difference, so the
     * rows add up to the invoice totals exactly.
     */
    public function byHsn(): Collection
    {
        $rows = [];
        foreach ($this->invoices as $invoice) {
            $items = $invoice->items->values();
            $subtotal = (float) $invoice->subtotal;
            $left = ['taxable' => $invoice->taxable_value, 'cgst' => (float) $invoice->cgst_amount, 'sgst' => (float) $invoice->sgst_amount, 'igst' => (float) $invoice->igst_amount];

            foreach ($items as $index => $item) {
                $last = $index === $items->count() - 1;
                $share = $subtotal > 0 ? (float) $item->amount / $subtotal : 0;
                $part = [];
                foreach ($left as $key => $remaining) {
                    $full = ['taxable' => $invoice->taxable_value, 'cgst' => (float) $invoice->cgst_amount, 'sgst' => (float) $invoice->sgst_amount, 'igst' => (float) $invoice->igst_amount][$key];
                    $part[$key] = $last ? round($remaining, 2) : round($full * $share, 2);
                    $left[$key] = round($remaining - $part[$key], 2);
                }

                $key = ($item->hsn_sac ?: '—').'|'.(float) $invoice->tax_rate;
                $rows[$key] ??= ['hsn' => $item->hsn_sac ?: '—', 'rate' => (float) $invoice->tax_rate, 'quantity' => 0.0, 'taxable' => 0.0, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => 0.0];
                $rows[$key]['quantity'] += (float) $item->quantity;
                foreach ($part as $name => $value) {
                    $rows[$key][$name] = round($rows[$key][$name] + $value, 2);
                }
            }
        }

        return collect($rows)->map(fn ($r) => $r + ['tax' => round($r['cgst'] + $r['sgst'] + $r['igst'], 2)])->sortBy(['hsn', 'rate'])->values();
    }
}
