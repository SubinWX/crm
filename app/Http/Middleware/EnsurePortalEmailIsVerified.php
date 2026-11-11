<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('portal')->user();

        return $user && ! $user->email_verified_at
            ? redirect()->route('portal.verify.notice')
            : $next($request);
    }
}
