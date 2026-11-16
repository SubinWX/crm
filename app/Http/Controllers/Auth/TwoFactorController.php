<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Second step of signing in for accounts with two-step login switched on. The password was
 * already checked by LoginController; nobody is signed in until the code here is right.
 */
class TwoFactorController extends Controller
{
    /** How long the password step stays valid while the code is typed. */
    public const MINUTES = 10;

    public function create(Request $request)
    {
        return $this->pending($request)
            ? view('auth.two-factor', ['recovery' => $request->boolean('recovery')])
            : redirect()->route('login');
    }

    public function store(Request $request)
    {
        $user = $this->pending($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => 'That took too long. Please sign in again.']);
        }

        $data = $request->validate(['code' => ['nullable', 'string', 'max:20'], 'recovery_code' => ['nullable', 'string', 'max:40']]);

        $throttleKey = 'two-factor|'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.']);
        }
        // A second limit per account, whatever address the attempts come from.
        $accountKey = 'two-factor-account|'.$user->id;
        if (RateLimiter::tooManyAttempts($accountKey, 10)) {
            throw ValidationException::withMessages(['code' => 'Too many wrong codes for this account. Try again in '.ceil(RateLimiter::availableIn($accountKey) / 60).' minutes.']);
        }

        $usedRecovery = false;
        if (filled($data['recovery_code'] ?? null)) {
            $usedRecovery = $user->useRecoveryCode($data['recovery_code']);
            $valid = $usedRecovery;
        } else {
            // Each code works once: a code someone saw being typed cannot be used again.
            $step = Totp::matchedStep($user->two_factor_secret, $data['code'] ?? '');
            $valid = $step !== null && Cache::add('two-factor-used|'.$user->id.'|'.$step, true, 180);
        }

        if (! $valid) {
            RateLimiter::hit($throttleKey, 60);
            RateLimiter::hit($accountKey, 900);
            activity('login_failed', "Wrong two-step code for {$user->email}", $user, [], $user->id);
            throw ValidationException::withMessages(filled($data['recovery_code'] ?? null)
                ? ['recovery_code' => 'That recovery code is not valid, or has already been used.']
                : ['code' => 'That code is not right. Codes change every 30 seconds, so check the app and try the newest one.']);
        }

        RateLimiter::clear($throttleKey);
        RateLimiter::clear($accountKey);
        $remember = (bool) $request->session()->pull('two_factor.remember', false);
        $area = $request->session()->pull('two_factor.area', 'admin');
        $request->session()->forget('two_factor');

        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        Cookie::queue(LoginController::AREA_COOKIE, $area, 60 * 24 * 365);

        if ($usedRecovery) {
            activity('two_factor', "{$user->name} signed in with a recovery code", $user);
            $request->session()->flash('status', 'You signed in with a recovery code. '.count($user->two_factor_recovery_codes ?? []).' left: make new ones under Two-step login if you are running low.');
        }

        return redirect()->intended(route('dashboard'));
    }

    /** The account that passed the password step, while that step is still fresh. */
    protected function pending(Request $request): ?User
    {
        $id = $request->session()->get('two_factor.user_id');
        if (! $id || now()->getTimestamp() > (int) $request->session()->get('two_factor.expires', 0)) {
            $request->session()->forget('two_factor');

            return null;
        }
        $user = User::find($id);

        return $user && $user->is_active && $user->hasTwoFactor() ? $user : null;
    }
}
