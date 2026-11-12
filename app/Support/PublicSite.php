<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * What every public page shares (the careers pages and the support portal): the logo and where
 * it links, the link and button at the top right, the footer and the social media links.
 * Edited by a Super Admin in Administration → Public Pages; stored as "site_…" settings.
 * Each page's own banner and wording live in PublicPage.
 */
class PublicSite
{
    public const FIELDS = [
        'site_name' => '',            // falls back to the app name
        'logo_link' => '',            // falls back to the page's own home
        'nav_label' => '',            // no extra link until a label and address are set
        'nav_url' => '',
        'button_label' => '',         // no button until a label and address are set
        'button_url' => '',
        'button_style' => 'solid',
        'button_shape' => 'rounded',
        'button_color' => '',         // falls back to the brand colour
        'button_new_tab' => '1',
        'footer_copyright' => '',     // falls back to "© year company. All rights reserved."
        'terms_label' => 'Terms & Conditions',
        'terms_url' => '',
        'privacy_label' => 'Privacy Policy',
        'privacy_url' => '',
        'social_facebook' => '', 'social_instagram' => '', 'social_linkedin' => '',
        'social_x' => '', 'social_youtube' => '', 'social_whatsapp' => '',
    ];

    public const BUTTON_STYLES = ['solid' => 'Solid (filled)', 'outline' => 'Outline', 'soft' => 'Soft tint'];

    public const BUTTON_SHAPES = ['rounded' => 'Rounded corners', 'pill' => 'Pill (fully round)', 'square' => 'Square'];

    public const SOCIALS = [
        'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn',
        'x' => 'X (Twitter)', 'youtube' => 'YouTube', 'whatsapp' => 'WhatsApp',
    ];

    public static function get(string $field): string
    {
        $value = Setting::get('site_'.$field);

        return ($value === null || $value === '') ? (string) (self::FIELDS[$field] ?? '') : (string) $value;
    }

    /** What the settings form shows: the values in use right now. */
    public static function stored(): array
    {
        return collect(self::FIELDS)->map(fn ($default, $field) => self::get($field))->all();
    }

    public static function logoPath(): ?string
    {
        $path = Setting::get('site_logo_path');

        return $path && Storage::disk('local')->exists($path) ? $path : null;
    }

    /** Public address of the logo; it changes when the file does, so browsers refresh it. */
    public static function logoUrl(): ?string
    {
        $path = self::logoPath();

        return $path ? route('site.logo', ['v' => substr(md5($path), 0, 8)]) : null;
    }

    public static function companyName(): string
    {
        return setting('company_name') ?: app_name();
    }

    public static function siteName(): string
    {
        return self::get('site_name') ?: app_name();
    }

    public static function copyright(): string
    {
        return self::get('footer_copyright') ?: '© '.date('Y').' '.self::companyName().'. All rights reserved.';
    }

    /** @return array<string, string> network => link, only those that are filled in */
    public static function socials(): array
    {
        return collect(array_keys(self::SOCIALS))->mapWithKeys(fn ($n) => [$n => self::get('social_'.$n)])->filter()->all();
    }

    /** @return array<int, array{label:string, url:string}> */
    public static function footerLinks(): array
    {
        return array_values(array_filter([
            self::get('terms_url') !== '' ? ['label' => self::get('terms_label'), 'url' => self::get('terms_url')] : null,
            self::get('privacy_url') !== '' ? ['label' => self::get('privacy_label'), 'url' => self::get('privacy_url')] : null,
        ]));
    }

    /** The extra link at the top right, or null when it is not set. */
    public static function navLink(): ?array
    {
        return self::get('nav_label') !== '' && self::get('nav_url') !== ''
            ? ['label' => self::get('nav_label'), 'url' => self::get('nav_url')] : null;
    }

    /** The button at the top right, or null when no label and link have been set. */
    public static function button(): ?array
    {
        if (self::get('button_label') === '' || self::get('button_url') === '') {
            return null;
        }

        return [
            'label' => self::get('button_label'),
            'url' => self::get('button_url'),
            'style' => self::buttonStyle(self::get('button_style'), self::get('button_shape'), self::get('button_color')),
            'new_tab' => self::get('button_new_tab') === '1',
        ];
    }

    /** Inline style for a button in the chosen style, shape and colour (the brand colour when none is picked). */
    public static function buttonStyle(string $style, string $shape, ?string $color): string
    {
        $color = Branding::validHex($color) ?: Branding::shade(600);
        $css = match ($style) {
            'outline' => "border:1.5px solid {$color};color:{$color};background:transparent;",
            'soft' => "border:1.5px solid transparent;color:{$color};background:color-mix(in srgb, {$color} 14%, white);",
            // White text on a light colour would be unreadable, so those get dark text.
            default => "border:1.5px solid {$color};background:{$color};color:".(Branding::contrastWithWhite($color) >= 3 ? '#fff' : '#1c1917').';',
        };

        return $css.'border-radius:'.(['pill' => '9999px', 'square' => '0', 'rounded' => '0.5rem'][$shape] ?? '0.5rem').';';
    }

    /** A link someone may type: a full web address, a page on this site ("/about"), an email or a phone link. */
    public static function validLink(?string $value): bool
    {
        $value = trim((string) $value);

        return $value === '' || (bool) preg_match('~^(https?://[^\s]+|/[^\s/][^\s]*|/|mailto:[^\s]+|tel:[0-9+()\-\s]+)$~i', $value);
    }

    public static function isExternal(string $url): bool
    {
        return str_starts_with($url, 'http');
    }
}
