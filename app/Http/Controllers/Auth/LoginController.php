<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /** Cookie that remembers which sign-in page this browser uses, so expired sessions return to the right one. */
    public const AREA_COOKIE = 'crm_login_area';

    /**
     * Two sign-in pages share this controller:
     *   /login   Super Admins and Sub Admins   (area "admin")
     *   /staff   Staff                         (area "staff")
     */
    public function create(Request $request)
    {
        return view('auth.login', ['area' => $this->area($request)]);
    }

    public function store(Request $request)
    {
        $area = $this->area($request);
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        // is_active is part of the credentials, so deactivated users cannot sign in.
        if (! Auth::guard('web')->attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['email' => 'These credentials do not match an active account.']);
        }

        // Each account has one sign-in page. The password was correct, so it is safe to point to the right one.
        $user = Auth::guard('web')->user();
        if ($user->loginArea() !== $area) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => $user->loginArea() === 'admin'
                    ? 'Admin accounts sign in on the admin page: '.route('login')
                    : 'Staff accounts sign in on the staff page: '.route('staff.login'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        Cookie::queue(self::AREA_COOKIE, $area, 60 * 24 * 365);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        $loginUrl = Auth::guard('web')->user()?->loginUrl() ?? route('login');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($loginUrl);
    }

    protected function area(Request $request): string
    {
        return $request->route()->defaults['area'] ?? 'admin';
    }
}
