<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Work to do and follow-ups to chase, for a client or for a lead. */
class Task extends Model
{
    use LogsActivity;

    public const STATUSES = ['todo' => 'To do', 'in_progress' => 'In progress', 'done' => 'Done'];

    protected $attributes = ['status' => 'todo'];

    protected $guarded = [];

    protected $casts = ['due_date' => 'date'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** The client it is for, or the lead while there is no client yet. */
    public function subjectName(): string
    {
        return $this->client?->name ?? $this->lead?->name ?? '—';
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', 'done');
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'done' && $this->due_date && days_until($this->due_date) < 0;
    }
}
