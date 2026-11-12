<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\EnsurePortalEmailIsVerified;
use App\Http\Middleware\EnsurePortalUserIsActive;
use App\Http\Middleware\EnsureTwoFactorSetUp;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['two-factor.setup' => EnsureTwoFactorSetUp::class, 'active' => EnsureUserIsActive::class, 'portal.active' => EnsurePortalUserIsActive::class, 'portal.verified' => EnsurePortalEmailIsVerified::class]);
        // Lets "Change Password" sign the account out on other devices.
        $middleware->authenticateSessions();
        // The support portal (/support/…) has its own sign-in page and home.
        $middleware->redirectGuestsTo(fn (Request $request) => match (true) {
            $request->is('support', 'support/*') => route('portal.login'),
            // Browsers that last signed in on the staff page go back there when their session ends.
            $request->cookie(LoginController::AREA_COOKIE) === 'staff' => route('staff.login'),
            default => route('login'),
        });
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('support', 'support/*') ? route('portal.tickets.index') : route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
