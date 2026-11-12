<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Models\Concerns\LogsActivity;
use App\Support\Gst;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use LogsActivity;

    protected $attributes = ['status' => 'draft'];

    public const STATUSES = [
        'draft' => 'Draft', 'sent' => 'Sent', 'partially_paid' => 'Partially Paid',
        'paid' => 'Paid', 'overdue' => 'Overdue', 'void' => 'Void',
    ];

    /** Invoices that count towards a client's pending amount. */
    public const ISSUED_STATUSES = ['sent', 'partially_paid', 'paid'];

    public const OPEN_STATUSES = ['sent', 'partially_paid'];

    protected $guarded = [];

    protected array $activityIgnored = ['amount_paid'];

    protected $casts = [
        'invoice_date' => 'date', 'planned_send_date' => 'date', 'due_date' => 'date', 'sent_at' => 'datetime',
        'subtotal' => 'decimal:2', 'tax_rate' => 'decimal:2', 'tax_amount' => 'decimal:2',
        'discount' => 'decimal:2', 'total' => 'decimal:2', 'amount_paid' => 'decimal:2', 'notes' => RichTextCast::class,
        'cgst_amount' => 'decimal:2', 'sgst_amount' => 'decimal:2', 'igst_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // New invoices pick up the GST details in force at that moment: the company's state, and the
        // client's GSTIN and state. Whoever creates the invoice (the form, or a quote) may set them first.
        static::creating(function (Invoice $invoice) {
            if (! Gst::enabled()) {
                return;
            }
            $client = $invoice->client;
            $invoice->supplier_state ??= Gst::companyState();
            $invoice->client_gstin ??= Gst::validGstin($client?->tax_id) ? strtoupper(trim($client->tax_id)) : null;
            $invoice->place_of_supply ??= $client?->state_code ?: Gst::stateFromGstin($client?->tax_id) ?: Gst::companyState();
        });

        static::updated(function (Invoice $invoice) {
            if ($invoice->wasChanged(['planned_send_date', 'status'])) {
                ReminderLog::query()
                    ->where('subject_type', 'invoice')->where('subject_id', $invoice->id)
                    ->whereNull('sent_at')->whereNull('cancelled_at')
                    ->update(['cancelled_at' => now()]);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** The accepted quote this invoice was raised from, if any. */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ClientContact::class, 'contact_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** intra (CGST + SGST), inter (IGST) or none (not a GST invoice). */
    public function gstKind(): string
    {
        return Gst::kind($this->supplier_state, $this->place_of_supply);
    }

    /** Value the tax is charged on. */
    public function getTaxableValueAttribute(): float
    {
        return round((float) $this->subtotal - (float) $this->discount, 2);
    }

    /** The tax lines to print: one "Tax" line, or CGST + SGST, or IGST. @return array<string, float> */
    public function taxLines(): array
    {
        $rate = (float) $this->tax_rate;
        $half = rtrim(rtrim(number_format($rate / 2, 2), '0'), '.');

        return match ($this->gstKind()) {
            'intra' => ["CGST ({$half}%)" => (float) $this->cgst_amount, "SGST ({$half}%)" => (float) $this->sgst_amount],
            'inter' => ["IGST ({$rate}%)" => (float) $this->igst_amount],
            default => ["Tax ({$rate}%)" => (float) $this->tax_amount],
        };
    }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true)
            && $this->due_date !== null
            && days_until($this->due_date) < 0;
    }

    /** Stored status, except open invoices past their due date read as "overdue". */
    public function getDisplayStatusAttribute(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }

    public function getDisplayStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->display_status] ?? $this->display_status;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_date')->where('due_date', '<', today_local()->toDateString());
    }

    public function scopeDisplayStatus(Builder $query, string $status): Builder
    {
        $today = today_local()->toDateString();

        return match ($status) {
            'overdue' => $query->overdue(),
            'sent', 'partially_paid' => $query->where('status', $status)
                ->where(fn ($q) => $q->whereNull('due_date')->orWhere('due_date', '>=', $today)),
            default => $query->where('status', $status),
        };
    }

    public static function nextNumber(): string
    {
        $prefix = setting('invoice_prefix', 'INV-').today_local()->format('Y').'-';
        $last = static::where('number', 'like', $prefix.'%')->orderByDesc('id')->value('number');
        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;
        do {
            $number = $prefix.str_pad((string) $sequence++, 4, '0', STR_PAD_LEFT);
        } while (static::where('number', $number)->exists());

        return $number;
    }

    /** Recalculate totals from the line items. */
    public function recalculateTotals(): void
    {
        $subtotal = round((float) $this->items()->sum('amount'), 2);
        $discount = min((float) $this->discount, $subtotal);
        $tax = round(($subtotal - $discount) * ((float) $this->tax_rate) / 100, 2);
        $split = Gst::split($tax, $this->supplier_state, $this->place_of_supply);
        $this->forceFill([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax_amount' => $tax,
            'cgst_amount' => $split['cgst'],
            'sgst_amount' => $split['sgst'],
            'igst_amount' => $split['igst'],
            'total' => round($subtotal - $discount + $tax, 2),
        ])->save();
    }

    /** Recalculate the paid amount and payment status after allocations change. */
    public function refreshPaymentStatus(): void
    {
        $paid = round((float) $this->allocations()->sum('amount'), 2);
        $attributes = ['amount_paid' => $paid];

        if (in_array($this->status, self::ISSUED_STATUSES, true)) {
            $attributes['status'] = match (true) {
                $paid >= (float) $this->total && (float) $this->total > 0 => 'paid',
                $paid > 0 => 'partially_paid',
                default => 'sent',
            };
        }

        $this->forceFill($attributes)->save();
    }

    public function markSent(?string $sentAt = null): void
    {
        if ($this->status !== 'draft') {
            return;
        }
        $this->forceFill(['status' => 'sent', 'sent_at' => $sentAt ?? now()])->save();
        $this->refreshPaymentStatus();
    }
}
