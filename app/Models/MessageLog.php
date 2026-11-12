<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One WhatsApp message to a client: what was sent, to which number, and whether it went through. */
class MessageLog extends Model
{
    public const EVENTS = [
        'hosting_due' => 'Hosting renewal', 'renewal_due' => 'Renewal', 'invoice_due' => 'Invoice payment',
        'manual' => 'Sent by hand', 'test' => 'Test message',
    ];

    protected $guarded = [];

    protected $attributes = ['channel' => 'whatsapp', 'status' => 'queued'];

    protected $casts = ['scheduled_date' => 'date', 'sent_at' => 'datetime', 'parameters' => 'array'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
