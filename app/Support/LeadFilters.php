<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/** Shared by the Leads screen and its CSV export. */
class LeadFilters
{
    public static function apply(Builder $query, array $filters): Builder
    {
        $f = fn (string $key) => $filters[$key] ?? null;

        return $query
            ->when($f('search'), fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('contact_name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->when($f('stage') === 'open', fn ($q) => $q->open())
            ->when($f('stage') && $f('stage') !== 'open' && $f('stage') !== 'all', fn ($q) => $q->where('stage', $f('stage')))
            ->when($f('owner'), fn ($q, $v) => $q->where('owner_id', $v))
            ->when($f('source'), fn ($q, $v) => $q->where('acquisition_source_id', $v));
    }
}
