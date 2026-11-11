<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RenewalLog extends Model
{
    protected $guarded = [];

    protected $casts = ['due_date' => 'date', 'paid_on' => 'date', 'next_date' => 'date', 'amount' => 'decimal:2'];

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
