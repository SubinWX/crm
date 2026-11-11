<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;

class SupportTicket extends Model
{
    use LogsActivity;

    public const TYPES = ['support' => 'Support request', 'new_work' => 'New work request', 'feedback' => 'Feedback'];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    public const STATUSES = [
        'open' => 'Open', 'in_progress' => 'In progress', 'waiting' => 'Waiting for customer',
        'resolved' => 'Resolved', 'closed' => 'Closed',
    ];

    /** Wording shown to the customer on the portal. */
    public const PORTAL_STATUSES = [
        'open' => 'Received', 'in_progress' => 'In progress', 'waiting' => 'Waiting for your reply',
        'resolved' => 'Resolved', 'closed' => 'Closed',
    ];

    public const ACTIVE_STATUSES = ['open', 'in_progress', 'waiting'];

    protected $attributes = ['status' => 'open', 'priority' => 'normal', 'type' => 'support'];

    protected $guarded = [];

    protected array $activityIgnored = ['last_activity_at'];

    protected $casts = ['last_activity_at' => 'datetime', 'closed_at' => 'datetime', 'rating' => 'integer'];

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(PortalUser::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function activityLabel(): string
    {
        return $this->number;
    }

    public function activityClientId(): ?int
    {
        return $this->client_id;
    }

    public function setStatus(string $status): void
    {
        $this->forceFill([
            'status' => $status,
            'closed_at' => in_array($status, ['resolved', 'closed'], true) ? ($this->closed_at ?? now()) : null,
            'last_activity_at' => now(),
        ])->save();
    }

    /** SUP-2026-0001. The unique index plus a retry keeps numbers unique under concurrent submissions. */
    public static function createWithNumber(array $attributes): self
    {
        $prefix = 'SUP-'.today_local()->format('Y').'-';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $last = static::where('number', 'like', $prefix.'%')->orderByDesc('id')->value('number');
            $next = ($last ? (int) substr($last, strlen($prefix)) : 0) + 1 + $attempt;

            try {
                return static::create($attributes + [
                    'number' => $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT),
                    'last_activity_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                continue;
            }
        }

        throw new \RuntimeException('Could not allocate a ticket number.');
    }
}
