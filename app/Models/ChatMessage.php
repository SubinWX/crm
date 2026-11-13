<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A message in the internal chat. No recipient means the team room, which everyone reads. */
class ChatMessage extends Model
{
    protected $guarded = [];

    protected $casts = ['read_at' => 'datetime'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /** Pictures are shown inside the conversation; other files are offered as a download. */
    public function isImage(): bool
    {
        return $this->attachment_path !== null && in_array($this->attachment_mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true);
    }

    public function attachmentSize(): string
    {
        return $this->attachment_size >= 1048576 ? round($this->attachment_size / 1048576, 1).' MB' : max(1, (int) round($this->attachment_size / 1024)).' KB';
    }

    /** The team room is open to everyone; a private message only to the two people in it. */
    public function canBeSeenBy(User $user): bool
    {
        return $this->recipient_id === null || $this->sender_id === $user->id || $this->recipient_id === $user->id;
    }

    /** Short text for the menu badge and the new-message popup. */
    public function preview(): string
    {
        return filled($this->body) ? Str::limit($this->body, 90) : ($this->isImage() ? 'Sent a picture' : 'Sent a file: '.$this->attachment_name);
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
