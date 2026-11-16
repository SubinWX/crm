<?php

namespace App\Models;

use App\Casts\RichTextCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A notice from the company to everyone, shown on the dashboard until it expires. */
class Announcement extends Model
{
    protected $guarded = [];

    protected $casts = ['body' => RichTextCast::class, 'pinned' => 'boolean', 'expires_on' => 'date'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Still to be shown: no end date, or one that has not passed. Pinned first, then newest. */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', today_local()->toDateString()))
            ->orderByDesc('pinned')->latest('id');
    }
}
