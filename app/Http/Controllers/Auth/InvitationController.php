<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public const EXPIRES_DAYS = 7;

    public function show(string $token)
    {
        return view('auth.accept-invitation', ['user' => $this->resolve($token), 'token' => $token]);
    }

    public function store(Request $request, string $token)
    {
        $user = $this->resolve($token);
        abort_unless($user, 404);

        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(8)]]);

        $user->forceFill([
            'password' => $data['password'],
            'invitation_token' => null,
            'email_verified_at' => now(),
        ])->save();

        activity('invitation_accepted', $user->name.' accepted their invitation', $user, [], $user->id);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    protected function resolve(string $token): ?User
    {
        return User::query()
            ->where('invitation_token', hash('sha256', $token))
            ->where('is_active', true)
            ->where('invited_at', '>=', now()->subDays(self::EXPIRES_DAYS))
            ->first();
    }
}
