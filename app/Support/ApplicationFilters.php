<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/** Shared by the Careers screen and its CSV export so both always agree. */
class ApplicationFilters
{
    public const SORTS = [
        'newest' => 'Newest first',
        'oldest' => 'Oldest first',
        'name' => 'Name A–Z',
        'rating' => 'Highest rated',
        'experience' => 'Most experienced',
        'interview' => 'Interview date',
    ];

    public static function apply(Builder $query, array $filters): Builder
    {
        $f = fn (string $key) => $filters[$key] ?? null;

        return $query
            ->when($f('search'), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")
                ->orWhere('current_company', 'like', "%{$s}%")->orWhere('location', 'like', "%{$s}%")))
            ->when($f('vacancy'), fn ($q, $v) => $v === 'general' ? $q->whereNull('job_vacancy_id') : $q->where('job_vacancy_id', $v))
            ->when($f('status'), fn ($q, $v) => $q->where('status', $v))
            // "Applied on" dates are picked in the business time zone; timestamps are stored in UTC.
            ->when($f('from') && local_date($f('from')), fn ($q) => $q->where('created_at', '>=', local_date($f('from'))->utc()))
            ->when($f('to') && local_date($f('to')), fn ($q) => $q->where('created_at', '<', local_date($f('to'))->addDay()->utc()));
    }

    public static function sort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->oldest()->oldest('id'),
            'name' => $query->orderBy('name')->latest('id'),
            'rating' => $query->orderByRaw('rating is null')->orderByDesc('rating')->latest('id'),
            'experience' => $query->orderByRaw('experience_years is null')->orderByDesc('experience_years')->latest('id'),
            'interview' => $query->orderByRaw('interview_at is null')->orderBy('interview_at')->latest('id'),
            default => $query->latest()->latest('id'),
        };
    }
}
