<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteItem extends Model
{
    protected $guarded = [];

    protected $casts = ['quantity' => 'decimal:2', 'unit_price' => 'decimal:2', 'amount' => 'decimal:2'];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** Line amount after the quote-level discount, before tax. This is what can be invoiced. */
    public function netAmount(float $discountPercent): float
    {
        return round((float) $this->amount * (1 - $discountPercent / 100), 2);
    }

    /** Already billed on invoices that are not void. */
    public function invoicedAmount(): float
    {
        return round((float) $this->invoiceItems()->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'void'))->sum('amount'), 2);
    }
}
