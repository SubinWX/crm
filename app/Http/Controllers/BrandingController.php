<?php

namespace App\Http\Controllers;

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
        $path = setting("{$kind}_path");
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
