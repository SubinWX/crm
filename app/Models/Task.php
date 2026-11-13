<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Work to do and follow-ups to chase, for a client or for a lead, optionally as part of a project. */
class Task extends Model
{
    use LogsActivity;

    public const STATUSES = ['todo' => 'To do', 'in_progress' => 'In progress', 'done' => 'Done'];

    protected $attributes = ['status' => 'todo'];

    protected $guarded = [];

    protected $casts = ['due_date' => 'date'];

    protected static function booted(): void
    {
        // A task made with just assignee_id (imports, seeders) still gets its person in the list.
        static::created(function (Task $task) {
            if ($task->assignee_id) {
                $task->assignees()->syncWithoutDetaching([$task->assignee_id]);
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** The first person on the task. Kept in step with assignees() by assignTo(). */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** Everyone working on the task. */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user')->orderBy('name');
    }

    /** Set who works on the task. @param array<int, int|string> $userIds */
    public function assignTo(array $userIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        $this->assignees()->sync($ids);
        $first = $ids ? User::whereIn('id', $ids)->orderBy('name')->value('id') : null;
        if ($this->assignee_id !== $first) {
            $this->update(['assignee_id' => $first]);
        }
        $this->unsetRelation('assignees');
    }

    public function isAssignedTo(?int $userId): bool
    {
        return $userId !== null && ($this->assignee_id === $userId || $this->assignees->contains('id', $userId));
    }

    public function assigneeNames(): string
    {
        return $this->assignees->pluck('name')->implode(', ') ?: 'Unassigned';
    }

    public function scopeAssignedTo(Builder $query, int|string $userId): Builder
    {
        return $query->whereHas('assignees', fn ($q) => $q->whereKey($userId));
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
