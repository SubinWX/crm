<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalUser;
use App\Notifications\Portal\PortalVerifyEmail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/**
 * Sign-up, sign-in and password reset for the public support portal.
 * Everything here uses the "portal" guard; team accounts are never involved.
 */
class AuthController extends Controller
{
    public function home()
    {
        return Auth::guard('portal')->check()
            ? redirect()->route('portal.tickets.index')
            : view('portal.home');
    }

    public function showLogin()
    {
        return view('portal.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        $key = 'portal-login|'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        if (! Auth::guard('portal')->attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'That email and password do not match a portal account.']);
        }

        RateLimiter::clear($key);

        return $this->signedIn($request);
    }

    public function showRegister()
    {
        return view('portal.register');
    }

    public function register(Request $request)
    {
        // Honeypot: real people never fill the hidden "website" field.
        if ($request->filled('website')) {
            return redirect()->route('portal.login');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:portal_users,email'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], ['email.unique' => 'An account with this email already exists. Sign in instead, or reset your password.']);

        $user = PortalUser::create([
            'name' => $data['name'],
            'email' => Str::lower($data['email']),
            'company' => $data['company'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);

        Auth::guard('portal')->login($user);
        $user->notify(new PortalVerifyEmail);

        return $this->signedIn($request);
    }

    /** "Check your inbox" page for signed-in accounts that have not confirmed their email yet. */
    public function verifyNotice()
    {
        return Auth::guard('portal')->user()->email_verified_at
            ? redirect()->route('portal.tickets.index')
            : view('portal.verify-email');
    }

    public function resendVerification(Request $request)
    {
        $user = Auth::guard('portal')->user();
        if ($user->email_verified_at) {
            return redirect()->route('portal.tickets.index');
        }

        $key = 'portal-verify|'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['email' => 'Please wait a few minutes before asking for another link.']);
        }
        RateLimiter::hit($key, 600);
        $user->notify(new PortalVerifyEmail);

        return back()->with('status', 'A new confirmation link is on its way.');
    }

    /** Target of the emailed link. The route is signed, so it works even if the person is not signed in on this device. */
    public function verify(Request $request, int $id, string $hash)
    {
        $user = PortalUser::findOrFail($id);
        abort_unless(hash_equals(sha1($user->email), $hash), 403);

        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return Auth::guard('portal')->id() === $user->id
            ? redirect()->route('portal.tickets.index')->with('status', 'Email confirmed. You can submit tickets now.')
            : redirect()->route('portal.login')->with('status', 'Email confirmed. Sign in to continue.');
    }

    public function logout(Request $request)
    {
        Auth::guard('portal')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('portal.home');
    }

    public function showForgot()
    {
        return view('portal.forgot-password');
    }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::broker('portal_users')->sendResetLink(['email' => $request->email, 'is_active' => true]);

        return back()->with('status', 'If that email has a portal account, a reset link is on its way.');
    }

    public function showReset(Request $request, string $token)
    {
        return view('portal.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::broker('portal_users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token') + ['is_active' => true],
            function (PortalUser $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('portal.login')->with('status', 'Password updated. You can sign in now.')
            : back()->withInput($request->only('email'))->withErrors(['email' => __($status)]);
    }

    protected function signedIn(Request $request)
    {
        $request->session()->regenerate();
        Auth::guard('portal')->user()->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('portal.tickets.index'));
    }
}
