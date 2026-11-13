<?php

namespace App\Models;

use App\Casts\RichTextCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Notes of a meeting with one person (a review, a one-to-one), kept on their profile. */
class MeetingNote extends Model
{
    protected $guarded = [];

    protected $casts = ['held_on' => 'date', 'shared' => 'boolean', 'notes' => RichTextCast::class];

    /** The person the meeting was with. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
