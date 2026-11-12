<?php

namespace App\Livewire\Account;

use App\Support\Totp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Account → Two-step login: switch it on with an authenticator app, get recovery codes, switch it off. */
#[Title('Two-step login')]
class TwoFactor extends Component
{
    public string $code = '';

    public string $password = '';

    /** Shown once, right after they are created. */
    public array $newCodes = [];

    public function start(): void
    {
        // Kept in the session, not the page, until a code proves the app has it.
        session(['two_factor.setup_secret' => Totp::generateSecret()]);
        $this->reset('code', 'newCodes');
        $this->resetValidation();
    }

    public function cancel(): void
    {
        session()->forget('two_factor.setup_secret');
        $this->reset('code');
        $this->resetValidation();
    }

    public function confirm(): void
    {
        $this->resetValidation();
        $secret = session('two_factor.setup_secret');
        abort_unless($secret, 422);

        if (! Totp::verify($secret, $this->code)) {
            $this->addError('code', 'That code is not right. Type the 6 digits your app shows now for this account.');

            return;
        }

        $user = Auth::user();
        $this->newCodes = Totp::recoveryCodes();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => $this->newCodes, 'two_factor_confirmed_at' => now()])->save();
        session()->forget('two_factor.setup_secret');
        $this->reset('code');

        activity('two_factor', "{$user->name} switched on two-step login", $user);
    }

    public function regenerate(): void
    {
        $this->resetValidation();
        if (! $this->passwordIsRight()) {
            return;
        }
        $user = Auth::user();
        abort_unless($user->hasTwoFactor(), 422);
        $this->newCodes = Totp::recoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $this->newCodes])->save();
        $this->reset('password');
        activity('two_factor', "{$user->name} made new two-step recovery codes", $user);
    }

    public function disable(): void
    {
        $this->resetValidation();
        $user = Auth::user();
        if ($user->isSuperAdmin() && setting('require_two_factor') === '1') {
            $this->addError('password', 'Two-step login is required for Super Admins, so it cannot be switched off here.');

            return;
        }
        if (! $this->passwordIsRight()) {
            return;
        }
        $user->clearTwoFactor();
        $this->reset('password', 'newCodes');
        activity('two_factor', "{$user->name} switched off two-step login", $user);
        $this->dispatch('toast', message: 'Two-step login switched off.');
    }

    protected function passwordIsRight(): bool
    {
        if (! Hash::check($this->password, (string) Auth::user()->password)) {
            $this->addError('password', 'That is not your current password.');

            return false;
        }

        return true;
    }

    public function render()
    {
        $user = Auth::user();
        $secret = $user->hasTwoFactor() ? null : session('two_factor.setup_secret');

        return view('livewire.account.two-factor', [
            'user' => $user,
            'enabled' => $user->hasTwoFactor(),
            'forced' => $user->mustSetUpTwoFactor(),
            'required' => $user->isSuperAdmin() && setting('require_two_factor') === '1',
            'secret' => $secret,
            'qr' => $secret ? Totp::qrSvg(Totp::uri($secret, $user->email, app_name())) : null,
            'codesLeft' => count($user->two_factor_recovery_codes ?? []),
        ]);
    }
}
