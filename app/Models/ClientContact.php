<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    public const ROLES = ['primary' => 'Primary', 'billing' => 'Billing', 'approvals' => 'Approvals', 'daily' => 'Daily communication', 'other' => 'Other'];

    protected $attributes = ['role' => 'other', 'is_primary' => false];

    protected $guarded = [];

    protected $casts = ['is_primary' => 'boolean'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function label(): string
    {
        return $this->name.' · '.(self::ROLES[$this->role] ?? $this->role).($this->email ? ' · '.$this->email : '');
    }
}
