<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One client in a bulk message, and what happened to their copy. */
class CampaignRecipient extends Model
{
    public const STATUSES = ['queued' => 'Waiting', 'sent' => 'Sent', 'failed' => 'Failed', 'skipped' => 'Skipped'];

    protected $guarded = [];

    protected $casts = ['sent_at' => 'datetime'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
