<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
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
        'discount' => 'decimal:2', 'total' => 'decimal:2', 'amount_paid' => 'decimal:2',
    ];

    protected static function booted(): void
    {
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
        $this->forceFill([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax_amount' => $tax,
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
