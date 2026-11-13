<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person's custom balance for one leave type in one year: a custom allowance and/or days added on top. */
class LeaveAllocation extends Model
{
    protected $guarded = [];

    protected $casts = ['days' => 'decimal:2', 'extra' => 'decimal:2', 'year' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'leave_type_id');
    }
}
