<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * When "Require two-step login for Super Admins" is on (Users page), a Super Admin who has
 * not set it up yet is taken to the setup page and cannot use anything else until it is done.
 */
class EnsureTwoFactorSetUp
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if ($user && $user->mustSetUpTwoFactor() && ! $request->routeIs('two-factor.setup', 'logout', 'branding.asset', 'users.avatar')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Set up two-step login first.'], 403)
                : redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
