<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HostingRenewal extends Model
{
    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date', 'period_end' => 'date', 'payment_due_date' => 'date',
        'paid_on' => 'date', 'next_payment_date' => 'date', 'amount' => 'decimal:2',
    ];

    public function hostingRecord(): BelongsTo
    {
        return $this->belongsTo(HostingRecord::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
