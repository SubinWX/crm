<?php

namespace App\Http\Controllers;

use App\Support\PublicPage;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the logo and favicon. Public on purpose: they appear on the sign-in pages
 * and the support portal before anyone has signed in. Nothing else is reachable here.
 */
class BrandingController extends Controller
{
    public function __invoke(string $kind)
    {
        abort_unless(in_array($kind, ['logo', 'icon', 'favicon'], true), 404);

        return $this->serve(setting("{$kind}_path"));
    }

    /** The logo shown on the public pages (careers and support portal). */
    public function siteLogo()
    {
        return $this->serve(setting('site_logo_path'));
    }

    /** The banner image of one public page. */
    public function siteBanner(string $page)
    {
        abort_unless(isset(PublicPage::PAGES[$page]), 404);

        return $this->serve(setting("{$page}_banner_path"));
    }

    /** A headshot used in an email signature. */
    public function emailAsset(string $file)
    {
        return $this->serve('email-assets/'.$file);
    }

    protected function serve(?string $path)
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $svg = str_ends_with(strtolower($path), '.svg');

        return response()->file(Storage::disk('local')->path($path), array_filter([
            'Content-Type' => $svg ? 'image/svg+xml' : (Storage::disk('local')->mimeType($path) ?: 'image/png'),
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
            // SVGs are checked on upload; this also stops one from running anything if opened directly.
            'Content-Security-Policy' => $svg ? "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox" : null,
        ]));
    }
}
