<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/** Shared by the payments screen and its PDF export so both always agree. */
class PaymentFilters
{
    public static function apply(Builder $query, array $filters): Builder
    {
        $f = fn (string $key) => $filters[$key] ?? null;

        return $query
            ->when($f('search'), fn ($q, $s) => $q->where(fn ($q) => $q->where('reference', 'like', "%{$s}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$s}%"))))
            ->when($f('client'), fn ($q, $v) => $q->where('client_id', $v))
            ->when($f('method'), fn ($q, $v) => $q->where('payment_method_id', $v))
            ->when($f('receiver'), fn ($q, $v) => $q->where('received_by', $v))
            ->when($f('from'), fn ($q, $v) => $q->where('payment_date', '>=', $v))
            ->when($f('to'), fn ($q, $v) => $q->where('payment_date', '<=', $v))
            ->when(filter_var($f('unallocated'), FILTER_VALIDATE_BOOLEAN), fn ($q) => $q->whereRaw(
                'payments.amount > (select coalesce(sum(pa.amount), 0) from payment_allocations pa where pa.payment_id = payments.id)'));
    }
}
