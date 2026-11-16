<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A template that raises the same invoice for a client every month, quarter or year: a retainer or a
 * maintenance plan. Each invoice is created as a draft on its date, ready to be checked and sent.
 */
class RecurringInvoice extends Model
{
    public const FREQUENCIES = ['monthly' => 'Every month', 'quarterly' => 'Every 3 months', 'yearly' => 'Every year'];

    public const MONTHS = ['monthly' => 1, 'quarterly' => 3, 'yearly' => 12];

    /** A template that was left alone for a long time catches up at most this many invoices in one go. */
    protected const MAX_CATCH_UP = 12;

    protected $guarded = [];

    protected $casts = ['next_date' => 'date', 'end_date' => 'date', 'last_generated_on' => 'date', 'active' => 'boolean', 'tax_rate' => 'decimal:2', 'due_days' => 'integer'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecurringInvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** Switched on, its date has come, and the client is not archived. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('active', true)->where('next_date', '<=', today_local()->toDateString())
            ->whereHas('client', fn ($q) => $q->whereNull('archived_at'));
    }

    /** What one invoice comes to before tax. */
    public function amount(): float
    {
        return round((float) $this->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_price), 2);
    }

    public function isFinished(): bool
    {
        return $this->end_date !== null && $this->next_date->gt($this->end_date);
    }

    /** "{month}" and "{year}" in a line become the month the invoice is for, e.g. "Maintenance - November 2026". */
    public static function fillPeriod(string $text, Carbon $date): string
    {
        return strtr($text, ['{month}' => $date->format('F'), '{year}' => $date->format('Y')]);
    }

    /**
     * Raise the invoice dated $this->next_date as a draft and move the template on one period.
     * Returns null when nothing is due (paused, finished, or no lines).
     */
    public function generate(): ?Invoice
    {
        return DB::transaction(function () {
            // Re-read under a lock so two requests at the same moment cannot both raise the same invoice.
            $template = static::whereKey($this->id)->lockForUpdate()->first();
            if (! $template || ! $template->active || $template->isFinished() || $template->items->isEmpty()) {
                return null;
            }
            $date = $template->next_date->copy();

            $invoice = Invoice::create([
                'number' => Invoice::nextNumber(), 'client_id' => $template->client_id, 'recurring_invoice_id' => $template->id,
                'invoice_date' => $date->toDateString(), 'planned_send_date' => $date->toDateString(),
                'due_date' => $date->copy()->addDays($template->due_days)->toDateString(),
                'currency' => setting('currency', 'INR'), 'tax_rate' => $template->tax_rate, 'notes' => $template->notes,
                'status' => 'draft', 'created_by' => $template->created_by,
            ]);
            foreach ($template->items as $position => $item) {
                $invoice->items()->create([
                    'description' => self::fillPeriod($item->description, $date), 'hsn_sac' => $item->hsn_sac,
                    'quantity' => $item->quantity, 'unit_price' => $item->unit_price,
                    'amount' => round((float) $item->quantity * (float) $item->unit_price, 2), 'sort_order' => $position,
                ]);
            }
            $invoice->recalculateTotals();

            $next = $date->copy()->addMonthsNoOverflow(self::MONTHS[$template->frequency] ?? 1);
            $template->update(['next_date' => $next->toDateString(), 'last_generated_on' => $date->toDateString()]
                + ($template->end_date && $next->gt($template->end_date) ? ['active' => false] : []));
            $this->refresh();

            return $invoice;
        });
    }

    /** Raise every invoice whose date has come. Safe to call often. @return int how many invoices were raised */
    public static function generateDue(): int
    {
        // Dates are compared as plain days (Y-m-d), so the business time zone cannot shift one across midnight.
        $count = 0;
        foreach (static::query()->due()->with('items')->get() as $template) {
            for ($i = 0; $i < self::MAX_CATCH_UP && $template->active && $template->next_date->toDateString() <= today_local()->toDateString(); $i++) {
                if (! $template->generate()) {
                    break;
                }
                $count++;
            }
        }

        return $count;
    }
}
