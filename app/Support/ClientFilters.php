<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Shared by the Clients screen and its CSV export. Always applies the Sub Admin "own clients" rule. */
class ClientFilters
{
    public static function apply(Builder $query, array $filters, ?User $user): Builder
    {
        $f = fn (string $key) => $filters[$key] ?? null;
        $archived = filter_var($f('archived'), FILTER_VALIDATE_BOOLEAN);

        return $query->visibleTo($user)
            ->when($archived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when($f('search'), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")->orWhere('contact_name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")->orWhere('phone1', 'like', "%{$s}%")
                ->orWhere('phone2', 'like', "%{$s}%")->orWhere('website', 'like', "%{$s}%")
                ->orWhere('location', 'like', "%{$s}%")))
            ->when($f('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($f('industry'), fn ($q, $v) => $q->where('industry_id', $v))
            ->when($f('source'), fn ($q, $v) => $q->where('acquisition_source_id', $v))
            ->when($f('manager'), fn ($q, $v) => $q->where('account_manager_id', $v))
            ->when($f('workType'), fn ($q, $v) => $q->whereHas('workTypes', fn ($q) => $q->where('lookups.id', $v)));
    }
}
