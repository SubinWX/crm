<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportMessage extends Model
{
    protected $guarded = [];

    protected $casts = ['is_internal' => 'boolean'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportAttachment::class);
    }

    public function fromTeam(): bool
    {
        return $this->user_id !== null;
    }

    public function authorName(): string
    {
        return $this->fromTeam() ? ($this->user?->name ?? 'Support team') : ($this->portalUser?->name ?? 'Customer');
    }
}
