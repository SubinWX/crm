<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/** Shared by the expenses screen and its CSV export so both always agree. */
class ExpenseFilters
{
    public static function apply(Builder $query, array $filters): Builder
    {
        $f = fn (string $key) => $filters[$key] ?? null;

        return $query
            ->when($f('search'), fn ($q, $s) => $q->where(fn ($q) => $q
                ->where('payee', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%")))
            ->when($f('category'), fn ($q, $v) => $q->where('category_id', $v))
            ->when($f('method'), fn ($q, $v) => $q->where('payment_method_id', $v))
            ->when($f('client'), fn ($q, $v) => $v === 'overhead' ? $q->whereNull('client_id') : $q->where('client_id', $v))
            ->when($f('from'), fn ($q, $v) => $q->where('expense_date', '>=', $v))
            ->when($f('to'), fn ($q, $v) => $q->where('expense_date', '<=', $v))
            ->when($f('recurring') === '1' || $f('recurring') === true, fn ($q) => $q->where('is_recurring', true));
    }
}
