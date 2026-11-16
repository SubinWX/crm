<?php

namespace App\Models;

use App\Casts\RichTextCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A knowledge-base article: a how-to or process document anyone in the team can read. */
class KbArticle extends Model
{
    protected $guarded = [];

    protected $casts = ['body' => RichTextCast::class];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** The writer and Super Admins may change or delete an article. */
    public function canBeEditedBy(User $user): bool
    {
        return $user->isSuperAdmin() || $this->created_by === $user->id;
    }
}
