<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/** Shared by the invoices screen and its PDF export so both always agree. */
class InvoiceFilters
{
    public static function apply(Builder $query, array $filters): Builder
    {
        $f = fn (string $key) => $filters[$key] ?? null;

        return $query
            ->when($f('search'), fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', "%{$s}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$s}%"))))
            ->when($f('client'), fn ($q, $v) => $q->where('client_id', $v))
            ->when($f('status'), fn ($q, $v) => $q->displayStatus($v))
            ->when($f('from'), fn ($q, $v) => $q->where('invoice_date', '>=', $v))
            ->when($f('to'), fn ($q, $v) => $q->where('invoice_date', '<=', $v));
    }
}
