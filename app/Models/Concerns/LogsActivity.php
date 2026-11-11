<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use App\Models\Client;
use Illuminate\Support\Str;

/**
 * Writes an activity log entry whenever the model is created, updated or deleted.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(fn ($model) => $model->writeActivity('created'));

        static::updated(function ($model) {
            $ignored = array_merge(['updated_at', 'remember_token', 'password', 'last_login_at', 'invitation_token', 'date_of_birth'], $model->activityIgnored ?? []);
            $changed = array_values(array_diff(array_keys($model->getChanges()), $ignored));
            if ($changed !== []) {
                $model->writeActivity('updated', ['changed' => $changed]);
            }
        });

        static::deleted(fn ($model) => $model->writeActivity('deleted'));
    }

    public function activityLabel(): string
    {
        return (string) ($this->name ?? $this->title ?? $this->number ?? $this->domain ?? '#'.$this->getKey());
    }

    public function activityClientId(): ?int
    {
        return $this instanceof Client ? $this->getKey() : ($this->client_id ?? null);
    }

    protected function writeActivity(string $action, array $properties = []): void
    {
        $type = Str::of(class_basename($this))->snake(' ')->toString();
        $description = ucfirst($type).' "'.$this->activityLabel().'" '.$action;
        if (! empty($properties['changed'])) {
            $description .= ' ('.implode(', ', array_map(fn ($f) => str_replace('_', ' ', $f), $properties['changed'])).')';
        }

        ActivityLog::record($action, Str::limit($description, 480), $this, $properties);
    }
}
