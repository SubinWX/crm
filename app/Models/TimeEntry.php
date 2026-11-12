<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Time one person spent on one project on one day. Stored in minutes so sums never drift. */
class TimeEntry extends Model
{
    /** How far back someone may add or change their own entries. Managers are not limited. */
    public const EDIT_DAYS = 14;

    protected $guarded = [];

    protected $casts = ['work_date' => 'date', 'minutes' => 'integer', 'is_billable' => 'boolean', 'started_at' => 'datetime', 'ended_at' => 'datetime'];

    /** A timer left running is stopped at this many hours, so a forgotten one cannot swallow a week. */
    public const MAX_TIMER_HOURS = 12;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** A timer that has been started and not stopped yet. */
    public function isRunning(): bool
    {
        return $this->started_at !== null && $this->ended_at === null;
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereNotNull('started_at')->whereNull('ended_at');
    }

    /** Finished entries only: what counts in totals and reports. */
    public function scopeFinished(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('started_at')->orWhereNotNull('ended_at'));
    }

    /** Length in seconds: exact for timed entries, from the typed hours otherwise, and still counting for a running timer. */
    public function seconds(): int
    {
        if ($this->started_at) {
            return max(0, (int) $this->started_at->diffInSeconds($this->ended_at ?? now()));
        }

        return $this->minutes * 60;
    }

    /** Stop a running timer now (or at the cap). Returns false when it was not running. */
    public function stop(): bool
    {
        if (! $this->isRunning()) {
            return false;
        }
        $end = now()->min($this->started_at->copy()->addHours(self::MAX_TIMER_HOURS));
        $this->update(['ended_at' => $end, 'minutes' => max(1, (int) round($this->started_at->diffInSeconds($end) / 60))]);

        return true;
    }

    /** "07:12:35" */
    public static function clock(int|float|null $seconds): string
    {
        $seconds = max(0, (int) $seconds);

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    public function getHoursAttribute(): float
    {
        return round($this->minutes / 60, 2);
    }

    /** "7h 30m", "45m", "8h". */
    public static function format(int|float|null $minutes): string
    {
        $minutes = (int) round((float) $minutes);
        [$h, $m] = [intdiv($minutes, 60), $minutes % 60];

        return $minutes === 0 ? '0h' : trim(($h ? $h.'h' : '').($m ? ' '.$m.'m' : ''));
    }
}
