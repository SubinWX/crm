<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\PortalUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * "Continue with Google" for the support portal. Only active when GOOGLE_CLIENT_ID
 * and GOOGLE_CLIENT_SECRET are set in .env.
 */
class GoogleController extends Controller
{
    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect()
    {
        abort_unless(self::enabled(), 404);

        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback(Request $request)
    {
        abort_unless(self::enabled(), 404);

        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('portal.login')->withErrors(['email' => 'Google sign-in was cancelled or failed. Please try again.']);
        }

        $email = Str::lower((string) $google->getEmail());
        if ($email === '' || ($google->user['email_verified'] ?? true) === false) {
            return redirect()->route('portal.login')->withErrors(['email' => 'Your Google account did not share a verified email address.']);
        }

        // Match on the Google id first, then on email so an existing password account is linked rather than duplicated.
        $user = PortalUser::where('google_id', $google->getId())->first()
            ?? PortalUser::where('email', $email)->first();

        if ($user && ! $user->is_active) {
            return redirect()->route('portal.login')->withErrors(['email' => 'This portal account has been disabled. Please contact us.']);
        }

        if ($user) {
            $user->forceFill(['google_id' => $google->getId(), 'email_verified_at' => $user->email_verified_at ?? now()])->save();
        } else {
            $user = PortalUser::create([
                'name' => $google->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'google_id' => $google->getId(),
                'email_verified_at' => now(),
            ]);
        }

        Auth::guard('portal')->login($user, true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('portal.tickets.index'));
    }
}
