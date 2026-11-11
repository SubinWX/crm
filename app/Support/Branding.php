<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * App name, colours, logo and favicon, all set in Settings → Branding.
 * The whole interface is styled from two colours: the brand colour (buttons, links,
 * highlights) and the sidebar colour. Every shade is derived from those two.
 */
class Branding
{
    public const DEFAULT_BRAND = '#15735c';

    public const DEFAULT_SIDEBAR = '#111917';

    /** Ready-made combinations offered in Settings: label => [brand, sidebar]. */
    public const PRESETS = [
        'Pine' => ['#15735c', '#111917'],
        'Indigo' => ['#4338ca', '#1e1b4b'],
        'Ocean' => ['#0369a1', '#0c1c2c'],
        'Violet' => ['#7c3aed', '#1f1535'],
        'Crimson' => ['#b91c1c', '#1c1917'],
        'Amber' => ['#b45309', '#1c1917'],
        'Slate' => ['#334155', '#0f172a'],
    ];

    public static function name(): string
    {
        return (string) (setting('app_name') ?: setting('company_name', config('app.name')));
    }

    public static function brand(): string
    {
        return self::validHex(setting('brand_color')) ?? self::DEFAULT_BRAND;
    }

    public static function sidebar(): string
    {
        return self::validHex(setting('sidebar_color')) ?? self::DEFAULT_SIDEBAR;
    }

    /** One shade of the brand colour (50, 100, 200, 500, 600, 700, 800). */
    public static function shade(int $step): string
    {
        return self::palette()["--color-brand-{$step}"] ?? self::brand();
    }

    /**
     * CSS variables that restyle the whole app. They override the defaults compiled into app.css.
     *
     * @return array<string, string>
     */
    public static function palette(): array
    {
        $brand = self::brand();
        $sidebar = self::sidebar();

        return [
            '--color-brand-50' => self::mix($brand, '#ffffff', 0.93),
            '--color-brand-100' => self::mix($brand, '#ffffff', 0.84),
            '--color-brand-200' => self::mix($brand, '#ffffff', 0.66),
            '--color-brand-500' => self::mix($brand, '#ffffff', 0.14),
            '--color-brand-600' => $brand,
            '--color-brand-700' => self::mix($brand, '#000000', 0.18),
            '--color-brand-800' => self::mix($brand, '#000000', 0.34),
            '--color-ink-900' => $sidebar,
            '--color-ink-800' => self::mix($sidebar, '#ffffff', 0.07),
            '--color-ink-700' => self::mix($sidebar, '#ffffff', 0.14),
        ];
    }

    public static function logoUrl(): ?string
    {
        return self::assetUrl('logo');
    }

    /** The small square mark used next to the app name. Falls back to the logo. */
    public static function iconUrl(): ?string
    {
        return self::assetUrl('icon') ?? self::assetUrl('logo');
    }

    /** Browser-tab icon. Falls back to the app icon when no separate favicon is uploaded. */
    public static function faviconUrl(): ?string
    {
        return self::assetUrl('favicon') ?? self::assetUrl('icon');
    }

    /** Logo embedded as a data URI, for PDFs (which cannot load images over the network). */
    public static function logoDataUri(): ?string
    {
        return self::pdfImage(setting('logo_path'), 1000, 1000)['src'] ?? null;
    }

    /**
     * An image prepared for a PDF: embedded as a data URI and scaled to fit inside a box (in PDF
     * pixels) without distortion, so a wide wordmark and a square mark both come out a sensible size.
     * Returns null when there is no image or it cannot be read.
     *
     * @return array{src:string, width:int, height:int}|null
     */
    public static function pdfImage(?string $path, int $maxWidth, int $maxHeight): ?array
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        $bytes = Storage::disk('local')->get($path);
        if (str_ends_with(strtolower($path), '.svg')) {
            $size = self::svgSize($bytes);

            return $size && self::svgIsSafe($bytes)
                ? self::fit('data:image/svg+xml;base64,'.base64_encode($bytes), (int) ceil($size[0]), (int) ceil($size[1]), $maxWidth, $maxHeight)
                : null;
        }
        $info = @getimagesizefromstring($bytes);
        if (! $info || $info[0] < 1 || $info[1] < 1) {
            return null;
        }
        [$width, $height, $mime] = [$info[0], $info[1], $info['mime']];

        // The PDF engine reads PNG and JPEG. Anything else (WebP) is converted to PNG, and very
        // large images are scaled down so the PDF stays small.
        $oversized = max($width, $height) > 1200;
        if (! in_array($mime, ['image/png', 'image/jpeg'], true) || $oversized) {
            $image = function_exists('imagecreatefromstring') ? @imagecreatefromstring($bytes) : false;
            if (! $image) {
                return in_array($mime, ['image/png', 'image/jpeg'], true)
                    ? self::fit('data:'.$mime.';base64,'.base64_encode($bytes), $width, $height, $maxWidth, $maxHeight)
                    : null;
            }
            $ratio = min(1, 1200 / max($width, $height));
            $target = imagecreatetruecolor(max(1, (int) round($width * $ratio)), max(1, (int) round($height * $ratio)));
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 255, 255, 255, 127));
            imagecopyresampled($target, $image, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
            ob_start();
            imagepng($target);
            $bytes = ob_get_clean();
            $mime = 'image/png';
        }

        return self::fit('data:'.$mime.';base64,'.base64_encode($bytes), $width, $height, $maxWidth, $maxHeight);
    }

    /** @return array{src:string, width:int, height:int} */
    protected static function fit(string $src, int $width, int $height, int $maxWidth, int $maxHeight): array
    {
        $scale = min($maxWidth / $width, $maxHeight / $height);

        return ['src' => $src, 'width' => max(1, (int) round($width * $scale)), 'height' => max(1, (int) round($height * $scale))];
    }

    /** The app logo sized for a PDF header. */
    public static function pdfLogo(int $maxWidth = 190, int $maxHeight = 44): ?array
    {
        return self::pdfImage(setting('logo_path'), $maxWidth, $maxHeight);
    }

    protected static function assetUrl(string $kind): ?string
    {
        $path = setting("{$kind}_path");

        // The file name changes on every upload, so it doubles as a cache-buster.
        return $path ? route('branding.asset', ['kind' => $kind, 'v' => substr(md5($path), 0, 8)]) : null;
    }

    /**
     * Whether an uploaded SVG is a plain drawing. SVG is a document format that can carry scripts
     * and load outside content, so anything beyond shapes, text and gradients is refused.
     */
    public static function svgIsSafe(string $contents): bool
    {
        if (! preg_match('/<svg[\s>]/i', $contents)) {
            return false;
        }

        $forbidden = [
            '/<\s*(script|foreignObject|iframe|embed|object|audio|video|animate|set|handler)\b/i',  // scripts and embedded content
            '/\bon[a-z]+\s*=/i',                                                                     // event handlers such as onload=
            '/(javascript|vbscript)\s*:/i',
            '/<!ENTITY|<!DOCTYPE[^>]*\[/i',                                                           // entity tricks
            '/<\?xml-stylesheet/i',
            '/(xlink:)?href\s*=\s*["\']\s*(?!#|data:image\/(png|jpe?g|webp);)/i',                   // links to anything but an internal #id or an inline bitmap
            '/url\(\s*["\']?\s*(?!#)/i',                                                           // CSS url() pointing outside the file
            '/@import/i',
        ];
        foreach ($forbidden as $pattern) {
            if (preg_match($pattern, $contents)) {
                return false;
            }
        }

        return true;
    }

    /** Width and height of an SVG from its width/height attributes or its viewBox. @return array{0:float,1:float}|null */
    public static function svgSize(string $contents): ?array
    {
        if (! preg_match('/<svg\b[^>]*>/i', $contents, $tag)) {
            return null;
        }
        $number = fn (string $attribute) => preg_match('/\b'.$attribute.'\s*=\s*["\']\s*([0-9.]+)(px)?\s*["\']/i', $tag[0], $m) ? (float) $m[1] : null;
        [$width, $height] = [$number('width'), $number('height')];
        if ((! $width || ! $height) && preg_match('/viewBox\s*=\s*["\']\s*[-0-9.]+[\s,]+[-0-9.]+[\s,]+([0-9.]+)[\s,]+([0-9.]+)/i', $tag[0], $box)) {
            [$width, $height] = [(float) $box[1], (float) $box[2]];
        }

        return $width > 0 && $height > 0 ? [$width, $height] : null;
    }

    public static function validHex(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : null;
    }

    /** Blend $amount (0–1) of $with into $hex. */
    public static function mix(string $hex, string $with, float $amount): string
    {
        [$a, $b] = [self::rgb($hex), self::rgb($with)];

        return sprintf('#%02x%02x%02x', ...array_map(fn ($i) => (int) round($a[$i] + ($b[$i] - $a[$i]) * $amount), [0, 1, 2]));
    }

    /** WCAG contrast ratio of a colour against white (1 = none, 21 = black on white). */
    public static function contrastWithWhite(string $hex): float
    {
        $channels = array_map(function ($c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));
        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        return 1.05 / ($luminance + 0.05);
    }

    /** @return array{0:int,1:int,2:int} */
    protected static function rgb(string $hex): array
    {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }
}
