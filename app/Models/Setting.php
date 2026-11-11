<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public const DEFAULTS = [
        'company_name' => 'Agency CRM',
        'company_address' => '',
        'company_email' => '',
        'company_phone' => '',
        'company_tax_id' => '',
        'currency' => 'INR',
        'timezone' => 'Asia/Kolkata',
        'invoice_prefix' => 'INV-',
        'invoice_default_tax_rate' => '18',
        'invoice_due_days' => '15',
        'invoice_footer' => 'Thank you for your business.',
        // Days before a renewal date on which Super Admins are reminded, per renewal type.
        'renewal_reminder_days_domain' => '60,7',
        'renewal_reminder_days_maintenance' => '30,7',
        'renewal_reminder_days_retainer' => '14,3',
        'renewal_reminder_days_other' => '30,7',
    ];

    /** Branding, edited in Settings → Branding. Empty means "use the built-in default". */
    public const BRANDING_KEYS = ['app_name', 'brand_color', 'sidebar_color', 'logo_path', 'icon_path', 'favicon_path'];

    protected static ?array $cache = null;

    public static function all($columns = ['*'])
    {
        return parent::all($columns);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (static::$cache === null) {
            try {
                static::$cache = static::query()->pluck('value', 'key')->all();
            } catch (\Throwable) {
                // Table not migrated yet (fresh install).
                return $default ?? (static::DEFAULTS[$key] ?? null);
            }
        }

        $value = static::$cache[$key] ?? null;

        return ($value === null || $value === '') ? ($default ?? (static::DEFAULTS[$key] ?? null)) : $value;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        static::$cache = null;
    }

    public static function flush(): void
    {
        static::$cache = null;
    }
}
