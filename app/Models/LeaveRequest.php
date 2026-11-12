<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A request for time off, approved or rejected by a Super Admin or someone with "team.manage". */
class LeaveRequest extends Model
{
    public const STATUSES = ['pending' => 'Waiting for approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled'];

    public const BADGES = ['pending' => 'badge-amber', 'approved' => 'badge-green', 'rejected' => 'badge-red', 'cancelled' => 'badge-gray'];

    public const DAY_PARTS = ['full' => 'Full day', 'first_half' => 'First half', 'second_half' => 'Second half'];

    protected $guarded = [];

    protected $attributes = ['status' => 'pending', 'day_part' => 'full'];

    protected $casts = ['from_date' => 'date', 'to_date' => 'date', 'days' => 'decimal:1', 'reviewed_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'leave_type_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Requests that touch any day between the two dates. */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->where('from_date', '<=', $to)->where('to_date', '>=', $from);
    }

    public function period(): string
    {
        $range = $this->from_date->equalTo($this->to_date) ? fmt_date($this->from_date) : fmt_date($this->from_date).' to '.fmt_date($this->to_date);

        return $range.($this->day_part !== 'full' ? ' ('.strtolower(self::DAY_PARTS[$this->day_part] ?? '').')' : '');
    }
}
