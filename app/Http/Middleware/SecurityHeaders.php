<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser-side protections added to every page:
 *  - the CRM cannot be shown inside a frame on someone else's site (clickjacking);
 *  - browsers do not guess a file's type, so an uploaded file cannot be run as a page;
 *  - the full address of a CRM page is not passed on to other sites in links;
 *  - once visited over HTTPS, browsers refuse plain HTTP for a year (live site only).
 * A header a controller has already set (e.g. the stricter one on uploaded logos) is left alone.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ];
        if ($request->isSecure() && app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
