<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interaction extends Model
{
    public const TYPES = ['call' => 'Call', 'email' => 'Email', 'whatsapp' => 'WhatsApp', 'meeting' => 'Meeting', 'note' => 'Note'];

    protected $guarded = [];

    protected $casts = ['happened_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
