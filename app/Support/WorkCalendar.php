<?php

namespace App\Support;

use App\Models\Holiday;
use Illuminate\Support\Carbon;

/**
 * Which days are working days: everything except the weekly off days chosen in the
 * Attendance settings (Sunday unless changed) and the company holidays.
 */
class WorkCalendar
{
    public const WEEKDAYS = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

    /** @return int[] day-of-week numbers (0 = Sunday) that are always off */
    public static function weeklyOff(): array
    {
        $value = setting('weekly_off_days');

        return collect(explode(',', (string) ($value ?? '0')))->map(fn ($d) => trim($d))->filter(fn ($d) => $d !== '' && ctype_digit($d) && (int) $d <= 6)
            ->map(fn ($d) => (int) $d)->unique()->values()->all();
    }

    /** @return array<string, string> date => holiday name, between the two dates */
    public static function holidays(string $from, string $to): array
    {
        return Holiday::whereBetween('date', [$from, $to])->orderBy('date')->get()->mapWithKeys(fn ($h) => [$h->date->toDateString() => $h->name])->all();
    }

    public static function isWeeklyOff(Carbon $date): bool
    {
        return in_array($date->dayOfWeek, self::weeklyOff(), true);
    }

    /** Working days from $from to $to inclusive. A half day on a single working date counts as 0.5. */
    public static function workingDays(string $from, string $to, string $dayPart = 'full'): float
    {
        $holidays = self::holidays($from, $to);
        $days = 0;
        for ($date = Carbon::parse($from)->startOfDay(), $end = Carbon::parse($to)->startOfDay(); $date->lte($end); $date->addDay()) {
            if (! self::isWeeklyOff($date) && ! isset($holidays[$date->toDateString()])) {
                $days++;
            }
        }

        return $dayPart !== 'full' && $from === $to ? $days * 0.5 : (float) $days;
    }
}
