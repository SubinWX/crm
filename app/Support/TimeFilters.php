<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/** Filters shared by the Timesheet Overview and its export, so the screen and the file always agree. */
class TimeFilters
{
    /** @param ?int $userId one person, or null for everyone */
    public static function apply(Builder $query, array $filters, ?int $userId): Builder
    {
        $f = fn (string $key) => $filters[$key] ?? null;
        $date = fn ($value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;

        return $query
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($date($f('from')), fn ($q, $v) => $q->where('work_date', '>=', $v))
            ->when($date($f('to')), fn ($q, $v) => $q->where('work_date', '<=', $v))
            ->when($f('task'), fn ($q, $s) => $q->where('description', 'like', "%{$s}%"))
            ->when(ctype_digit((string) $f('project')), fn ($q) => $q->where('project_id', (int) $f('project')))
            ->when(ctype_digit((string) $f('client')), fn ($q) => $q->whereHas('project', fn ($q) => $q->where('client_id', (int) $f('client'))));
    }
}
