<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * The parts of a public page that are its own: the banner at the top (image, heading,
 * description) and the call-to-action band above the footer. One set for the careers pages
 * and one for the support portal, edited from each area's "Page Settings".
 * Stored as "{page}_…" settings; an empty value means "use the built-in wording".
 */
class PublicPage
{
    /** page key => [name, permission needed to edit it, public route] */
    public const PAGES = [
        'careers' => ['Careers page', 'careers.manage', 'careers.index'],
        'support' => ['Support portal', 'support.manage', 'portal.home'],
    ];

    public const ALIGNMENTS = ['left' => 'Left', 'center' => 'Centre'];

    public const HEIGHTS = ['sm' => 'Short', 'md' => 'Medium', 'lg' => 'Tall'];

    public const CTA_STYLES = ['brand' => 'Brand colour', 'dark' => 'Dark', 'light' => 'Light'];

    /** Optional lines: clearing one hides it instead of bringing the default wording back. */
    public const HIDEABLE = ['tagline', 'banner_text', 'cta_text'];

    public const HIDDEN = '__none';

    protected const DEFAULTS = [
        'careers' => [
            'tagline' => 'Careers',
            'banner_heading' => 'Careers',
            'banner_text' => 'Join our team. Explore the open positions below and apply with your resume.',
            'cta_heading' => 'Want to know more about working with us?',
            'cta_text' => 'Have a question about a role or our hiring process? We are happy to help.',
        ],
        'support' => [
            'tagline' => 'Support Portal',
            'banner_heading' => 'How can we help with your project?',
            'banner_text' => 'Raise a support request for work we are doing for you, ask for new work, or share feedback. Every request gets a ticket number so you can follow it to the finish.',
            'cta_heading' => 'Need something new for your business?',
            'cta_text' => 'Tell us about your next website, campaign or idea and we will get back to you with a plan.',
        ],
    ];

    protected const COMMON = [
        'banner_overlay' => '55', 'banner_align' => 'left', 'banner_height' => 'md',
        'cta_enabled' => '0', 'cta_button_label' => 'Contact us', 'cta_button_url' => '', 'cta_style' => 'brand', 'cta_new_tab' => '1',
    ];

    public function __construct(public readonly string $page)
    {
        abort_unless(isset(self::PAGES[$page]), 404);
    }

    public static function for(string $page): self
    {
        return new self($page);
    }

    /** @return string[] */
    public static function fields(): array
    {
        return array_merge(array_keys(self::DEFAULTS['careers']), array_keys(self::COMMON));
    }

    public function name(): string
    {
        return self::PAGES[$this->page][0];
    }

    public function permission(): string
    {
        return self::PAGES[$this->page][1];
    }

    public function url(): string
    {
        return route(self::PAGES[$this->page][2]);
    }

    public function default(string $field): string
    {
        return (string) (self::DEFAULTS[$this->page][$field] ?? self::COMMON[$field] ?? '');
    }

    public function get(string $field): string
    {
        $value = Setting::get($this->page.'_'.$field);
        if ($value === self::HIDDEN) {
            return '';
        }

        return ($value === null || $value === '') ? $this->default($field) : (string) $value;
    }

    public function stored(): array
    {
        return collect(self::fields())->mapWithKeys(fn ($field) => [$field => $this->get($field)])->all();
    }

    public function put(string $field, ?string $value): void
    {
        $value = trim((string) $value);
        Setting::put($this->page.'_'.$field, $value === '' && in_array($field, self::HIDEABLE, true) ? self::HIDDEN : $value);
    }

    public function bannerPath(): ?string
    {
        $path = Setting::get($this->page.'_banner_path');

        return $path && Storage::disk('local')->exists($path) ? $path : null;
    }

    public function bannerUrl(): ?string
    {
        $path = $this->bannerPath();

        return $path ? route('site.banner', ['page' => $this->page, 'v' => substr(md5($path), 0, 8)]) : null;
    }

    /** The call-to-action band above the footer, or null when it is off or has no button link. */
    public function cta(): ?array
    {
        if ($this->get('cta_enabled') !== '1' || $this->get('cta_button_url') === '' || $this->get('cta_button_label') === '') {
            return null;
        }

        return [
            'heading' => $this->get('cta_heading'), 'text' => $this->get('cta_text'),
            'label' => $this->get('cta_button_label'), 'url' => $this->get('cta_button_url'),
            'style' => array_key_exists($this->get('cta_style'), self::CTA_STYLES) ? $this->get('cta_style') : 'brand',
            'new_tab' => $this->get('cta_new_tab') === '1',
        ];
    }
}
