<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Project extends Model
{
    use LogsActivity;

    public const KINDS = ['project' => 'Project', 'service' => 'Ongoing service'];

    public const STATUSES = ['planned' => 'Planned', 'active' => 'Active', 'on_hold' => 'On hold', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    protected $attributes = ['kind' => 'project', 'status' => 'planned'];

    protected $guarded = [];

    protected $casts = ['deadline' => 'date', 'next_milestone_date' => 'date'];

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

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['planned', 'active', 'on_hold']);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['planned', 'active', 'on_hold'], true) && $this->deadline && days_until($this->deadline) < 0;
    }
}
