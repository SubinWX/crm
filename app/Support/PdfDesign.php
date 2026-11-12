<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The look shared by every PDF the CRM produces (invoices, quotes and proposals, and the
 * invoice / payment / expense reports): logo, header, footer, page border and accent colour.
 * Edited by a Super Admin in Administration → PDF Settings; stored as "pdf_…" settings.
 * An empty value falls back to the company details from Settings → General.
 */
class PdfDesign
{
    public const FIELDS = [
        'header_style' => 'classic',
        'header_title' => '',        // falls back to the company name
        'header_tagline' => '',
        'header_details' => '',      // falls back to address, email · phone, GSTIN
        'footer_text' => '',         // falls back to the invoice footer note in Settings → General
        'footer_contact' => '',      // falls back to email · phone
        'page_numbers' => '1',
        'page_border' => '1',
        'table_style' => 'grid',
        'accent_color' => '',        // falls back to the brand colour
    ];

    public const HEADER_STYLES = ['classic' => 'Classic: logo and details with a coloured line', 'band' => 'Band: solid colour bar with white text', 'centered' => 'Centred: logo and details in the middle'];

    public const TABLE_STYLES = ['grid' => 'Grid: every cell bordered', 'lines' => 'Lines: rows separated by lines only'];

    /** Space reserved on every page for the header and footer, in PDF points. */
    public const HEADER_HEIGHT = 86;

    public const FOOTER_HEIGHT = 54;

    public static function get(string $field): string
    {
        $value = Setting::get('pdf_'.$field);

        return ($value === null || $value === '') ? (string) (self::FIELDS[$field] ?? '') : (string) $value;
    }

    public static function stored(): array
    {
        return collect(self::FIELDS)->map(fn ($default, $field) => self::get($field))->all();
    }

    public static function accent(): string
    {
        return Branding::validHex(self::get('accent_color')) ?: Branding::shade(700);
    }

    /** A very light tint of the accent colour, for panel and table heading backgrounds. */
    public static function tint(float $amount = 0.9): string
    {
        return Branding::mix(self::accent(), '#ffffff', $amount);
    }

    /** White or near-black, whichever reads better on the accent colour. */
    public static function onAccent(): string
    {
        return Branding::contrastWithWhite(self::accent()) >= 3 ? '#ffffff' : '#1c1917';
    }

    /** The PDF logo, or the app logo when no separate one has been uploaded. Sized to fit the given box. */
    public static function logo(int $maxWidth = 170, int $maxHeight = 50): ?array
    {
        return Branding::pdfImage(Setting::get('pdf_logo_path'), $maxWidth, $maxHeight) ?? Branding::pdfLogo($maxWidth, $maxHeight);
    }

    public static function hasOwnLogo(): bool
    {
        return Branding::pdfImage(Setting::get('pdf_logo_path'), 10, 10) !== null;
    }

    public static function title(): string
    {
        return self::get('header_title') ?: (string) setting('company_name', config('app.name'));
    }

    /** @return string[] up to four lines of address and contact details under the title */
    public static function headerLines(): array
    {
        $custom = trim(self::get('header_details'));
        $lines = $custom !== ''
            ? preg_split('/\r\n|\r|\n/', $custom)
            : [
                trim(preg_replace('/\s*[\r\n]+\s*/', ', ', (string) setting('company_address'))),
                collect([setting('company_email'), setting('company_phone')])->filter()->implode('  ·  '),
                filled(setting('company_tax_id')) ? 'GSTIN: '.setting('company_tax_id') : '',
            ];

        return array_slice(array_values(array_filter(array_map('trim', $lines), fn ($line) => $line !== '')), 0, 4);
    }

    /** @return string[] up to two lines printed at the bottom of every page */
    public static function footerLines(): array
    {
        $text = trim(self::get('footer_text')) ?: trim((string) setting('invoice_footer'));
        $contact = trim(self::get('footer_contact')) ?: collect([setting('company_email'), setting('company_phone')])->filter()->implode('  ·  ');

        return array_slice(array_values(array_filter([...preg_split('/\r\n|\r|\n/', $text), $contact], fn ($line) => trim((string) $line) !== '')), 0, 3);
    }

    public static function border(): bool
    {
        return self::get('page_border') === '1';
    }

    public static function pageNumbers(): bool
    {
        return self::get('page_numbers') === '1';
    }
}
