<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message in the internal chat. No recipient means the team room, which everyone reads. */
class ChatMessage extends Model
{
    protected $guarded = [];

    protected $casts = ['read_at' => 'datetime'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** The private conversation between two people, both directions. */
    public function scopeBetween(Builder $query, int $a, int $b): Builder
    {
        return $query->where(fn ($q) => $q->where(fn ($q) => $q->where('sender_id', $a)->where('recipient_id', $b))
            ->orWhere(fn ($q) => $q->where('sender_id', $b)->where('recipient_id', $a)));
    }

    /** How many messages $user has not read yet: direct messages to them, plus team-room messages since they last looked. */
    public static function unreadFor(User $user): int
    {
        return static::where('recipient_id', $user->id)->whereNull('read_at')->count()
            + static::whereNull('recipient_id')->where('sender_id', '!=', $user->id)
                ->when($user->team_chat_read_at, fn ($q, $at) => $q->where('created_at', '>', $at))->count();
    }
}
