<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['properties' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, string $description, ?Model $subject = null, array $properties = [], ?int $userId = null): self
    {
        $clientId = null;
        if ($subject) {
            $clientId = method_exists($subject, 'activityClientId')
                ? $subject->activityClientId()
                : ($subject instanceof Client ? $subject->getKey() : ($subject->client_id ?? null));
        }

        return static::create([
            // Always the team member ("web" guard). On support portal requests the default guard is
            // the portal one, whose ids belong to a different table, so those entries are logged as System.
            'user_id' => $userId ?? auth('web')->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'client_id' => $clientId,
            'description' => $description,
            'properties' => $properties ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
