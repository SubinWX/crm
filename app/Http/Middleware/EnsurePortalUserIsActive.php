<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('portal')->user();

        if ($user && ! $user->is_active) {
            Auth::guard('portal')->logout();

            return redirect()->route('portal.login')->withErrors(['email' => 'This portal account has been disabled. Please contact us.']);
        }

        return $next($request);
    }
}
