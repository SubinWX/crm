<?php

namespace App\Support;

use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use App\Models\Lookup;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * How much leave of each type a person has in a year.
 *
 * Automatic: the leave type's yearly days (a higher figure after some years with the company),
 * given all at once, half on 1 January and half on 1 July, or not at all (added by hand only).
 * Custom: a manager may set a different yearly figure for one person, and add days on top.
 * A type without a yearly figure (loss of pay) has no limit; only what was taken is counted.
 */
class LeaveBalance
{
    public const ACCRUALS = [
        'yearly' => 'All at the start of the year',
        'half_yearly' => 'Half on 1 January, half on 1 July',
        'manual' => 'Only what a manager adds',
    ];

    public static function types(bool $activeOnly = true): Collection
    {
        return Lookup::query()->where('type', 'leave_type')->when($activeOnly, fn ($q) => $q->where('is_active', true))->orderBy('sort_order')->orderBy('name')->get();
    }

    public static function hasLimit(Lookup $type): bool
    {
        return $type->is_paid && $type->yearly_days !== null;
    }

    /** The yearly figure the type's own rule gives this person, before accrual and custom changes. */
    public static function automaticYearly(User $user, Lookup $type, int $year): float
    {
        if ($type->accrual === 'manual') {
            return 0.0;
        }
        $days = (float) $type->yearly_days;
        // The higher figure applies from the year in which the person completes the required years.
        if ($type->senior_days !== null && $type->senior_after_years && $user->joined_on
            && $user->joined_on->copy()->addYears((int) $type->senior_after_years)->year <= $year) {
            $days = (float) $type->senior_days;
        }

        return $days;
    }

    /** Days available so far in that year (null = no limit). */
    public static function allowance(User $user, Lookup $type, int $year, ?LeaveAllocation $allocation = null, ?Carbon $today = null): ?float
    {
        if (! self::hasLimit($type)) {
            return null;
        }
        $today ??= today_local();
        $yearly = $allocation?->days !== null ? (float) $allocation->days : self::automaticYearly($user, $type, $year);
        // Half-yearly types release the second half on 1 July.
        if ($type->accrual === 'half_yearly' && ($year > $today->year || ($year === $today->year && $today->month < 7))) {
            $yearly /= 2;
        }

        return round($yearly + (float) ($allocation?->extra ?? 0), 2);
    }

    /**
     * One row per leave type for this person and year.
     *
     * @return Collection<int, array{type: Lookup, code: string, allowance: ?float, yearly: ?float, custom: bool, extra: float, used: float, pending: float, left: ?float}>
     */
    public static function for(User $user, int $year, ?Collection $types = null, ?Collection $allocations = null, ?Collection $requests = null): Collection
    {
        $types ??= self::types();
        $allocations ??= LeaveAllocation::where('user_id', $user->id)->where('year', $year)->get();
        $requests ??= LeaveRequest::where('user_id', $user->id)->whereIn('status', ['approved', 'pending'])->whereYear('from_date', $year)->get(['leave_type_id', 'status', 'days']);
        $allocations = $allocations->keyBy('leave_type_id');

        return $types->map(function (Lookup $type) use ($user, $year, $allocations, $requests) {
            $allocation = $allocations[$type->id] ?? null;
            $mine = $requests->where('leave_type_id', $type->id);
            $used = (float) $mine->where('status', 'approved')->sum('days');
            $pending = (float) $mine->where('status', 'pending')->sum('days');
            $allowance = self::allowance($user, $type, $year, $allocation);

            return [
                'type' => $type, 'code' => $type->code ?: mb_strtoupper(mb_substr($type->name, 0, 3)),
                'allowance' => $allowance,
                'yearly' => self::hasLimit($type) ? ($allocation?->days !== null ? (float) $allocation->days : self::automaticYearly($user, $type, $year)) : null,
                'custom' => $allocation?->days !== null, 'extra' => (float) ($allocation?->extra ?? 0),
                'used' => $used, 'pending' => $pending,
                'left' => $allowance === null ? null : round($allowance - $used, 2),
            ];
        })->values();
    }

    /** Balances for several people at once. @return array<int, Collection> keyed by user id */
    public static function forPeople(Collection $users, int $year): array
    {
        $types = self::types();
        $allocations = LeaveAllocation::whereIn('user_id', $users->pluck('id'))->where('year', $year)->get()->groupBy('user_id');
        $requests = LeaveRequest::whereIn('user_id', $users->pluck('id'))->whereIn('status', ['approved', 'pending'])->whereYear('from_date', $year)->get(['user_id', 'leave_type_id', 'status', 'days'])->groupBy('user_id');

        return $users->mapWithKeys(fn (User $u) => [$u->id => self::for($u, $year, $types, $allocations[$u->id] ?? collect(), $requests[$u->id] ?? collect())])->all();
    }
}
