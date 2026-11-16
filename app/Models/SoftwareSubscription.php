<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A tool or service the company pays for regularly: what it costs, when it renews and who looks after it. */
class SoftwareSubscription extends Model
{
    public const CYCLES = ['monthly' => 'Monthly', 'quarterly' => 'Every 3 months', 'yearly' => 'Yearly'];

    /** How many months one payment covers. */
    public const MONTHS = ['monthly' => 1, 'quarterly' => 3, 'yearly' => 12];

    public const STATUSES = ['active' => 'Active', 'cancelled' => 'Cancelled'];

    protected $guarded = [];

    protected $casts = ['cost' => 'decimal:2', 'renews_on' => 'date', 'seats' => 'integer'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** What it costs per month, whatever the billing cycle. */
    public function monthlyCost(): float
    {
        return (float) $this->cost / (self::MONTHS[$this->billing_cycle] ?? 1);
    }
}
