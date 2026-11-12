<?php

namespace App\Models;

use App\Casts\RichTextCast;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use LogsActivity;

    public const KINDS = ['project' => 'Project', 'service' => 'Ongoing service'];

    public const STATUSES = ['planned' => 'Planned', 'active' => 'Active', 'on_hold' => 'On hold', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    protected $attributes = ['kind' => 'project', 'status' => 'planned'];

    protected $guarded = [];

    protected $casts = [
        'deadline' => 'date', 'next_milestone_date' => 'date', 'start_date' => 'date', 'estimated_hours' => 'decimal:2',
        'description' => RichTextCast::class,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** The people working on this project. With nobody chosen, anyone on the team may log time to it. */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->orderBy('name');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /** Projects this person may log time against: open ones they are on, or that have no fixed team. */
    public function scopeLoggableBy(Builder $query, User $user): Builder
    {
        return $query->open()->where(fn ($q) => $q->whereDoesntHave('members')->orWhereHas('members', fn ($q) => $q->whereKey($user->id))->orWhere('owner_id', $user->id));
    }

    /** Hours used as a share of the estimate (may pass 100), or null when there is no estimate. */
    public function progress(int $loggedMinutes): ?int
    {
        return (float) $this->estimated_hours > 0 ? (int) round($loggedMinutes / ((float) $this->estimated_hours * 60) * 100) : null;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['planned', 'active', 'on_hold']);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['planned', 'active', 'on_hold'], true) && $this->deadline && days_until($this->deadline) < 0;
    }
}
