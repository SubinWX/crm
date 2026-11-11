<?php

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Support\Branding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('crm_tz')) {
    /** The business time zone (Asia/Kolkata unless changed in Settings). */
    function crm_tz(): string
    {
        return setting('timezone', 'Asia/Kolkata') ?: 'Asia/Kolkata';
    }
}

if (! function_exists('today_local')) {
    /** Start of "today" in the business time zone. */
    function today_local(): Carbon
    {
        return Carbon::now(crm_tz())->startOfDay();
    }
}

if (! function_exists('local_date')) {
    /** Interpret a date-only value as midnight in the business time zone. */
    function local_date(mixed $date): ?Carbon
    {
        if (blank($date)) {
            return null;
        }
        $string = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);

        return Carbon::createFromFormat('Y-m-d', $string, crm_tz())->startOfDay();
    }
}

if (! function_exists('days_until')) {
    function days_until(mixed $date): ?int
    {
        $target = local_date($date);

        return $target ? (int) round(today_local()->diffInDays($target, false)) : null;
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date(mixed $date, string $fallback = '—'): string
    {
        if (blank($date)) {
            return $fallback;
        }

        return ($date instanceof DateTimeInterface ? Carbon::instance($date) : Carbon::parse($date))->format('d M Y');
    }
}

if (! function_exists('fmt_datetime')) {
    /** Timestamps are stored in UTC and shown in the business time zone. */
    function fmt_datetime(mixed $value, string $fallback = '—'): string
    {
        if (blank($value)) {
            return $fallback;
        }

        return Carbon::parse($value)->timezone(crm_tz())->format('d M Y, g:i A');
    }
}

if (! function_exists('money')) {
    function money(mixed $amount, ?string $currency = null): string
    {
        $currency = strtoupper($currency ?: setting('currency', 'INR'));
        $amount = (float) $amount;

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($currency === 'INR' ? 'en_IN' : 'en_US', NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency($amount, $currency);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return $currency.' '.number_format($amount, 2);
    }
}

if (! function_exists('activity')) {
    function activity(string $action, string $description, ?Model $subject = null, array $properties = [], ?int $userId = null): ActivityLog
    {
        return ActivityLog::record($action, $description, $subject, $properties, $userId);
    }
}

if (! function_exists('app_name')) {
    /** The name shown in the sidebar, browser tab, sign-in pages and emails. Set in Settings → Branding. */
    function app_name(): string
    {
        return Branding::name();
    }
}
