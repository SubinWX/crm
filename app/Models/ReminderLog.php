<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scheduled_date' => 'date', 'dispatched_at' => 'datetime', 'sent_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
