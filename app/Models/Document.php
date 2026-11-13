<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file kept in Office → Documents: a person's own paper (ID proof, certificate) or a company document shared with everyone. */
class Document extends Model
{
    public const CATEGORIES = [
        'identity' => 'Identity proof', 'education' => 'Education', 'experience' => 'Experience', 'employment' => 'Offer / appointment letter',
        'bank' => 'Bank and tax', 'company' => 'Company document', 'other' => 'Other',
    ];

    protected $guarded = [];

    protected $casts = ['shared' => 'boolean', 'size' => 'integer'];

    /** The person the document belongs to. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /** Documents $viewer may open: their own, ones they added, shared ones, and those of the people they manage. */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->isSuperAdmin()) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('shared', true)->orWhere('user_id', $viewer->id)->orWhere('added_by', $viewer->id)
            ->orWhereIn('user_id', $viewer->managedPeople()->select('id')));
    }

    public function canBeOpenedBy(User $viewer): bool
    {
        return static::query()->visibleTo($viewer)->whereKey($this->id)->exists();
    }

    /** The owner, whoever added it, their managers and Super Admins may delete it. */
    public function canBeDeletedBy(User $viewer): bool
    {
        return $viewer->isSuperAdmin() || $this->user_id === $viewer->id || $this->added_by === $viewer->id
            || $viewer->managedPeople()->whereKey($this->user_id)->exists();
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->original_name, PATHINFO_EXTENSION) ?: 'file');
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1).' MB' : max(1, (int) round($this->size / 1024)).' KB';
    }
}
