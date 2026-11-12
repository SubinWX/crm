<x-layouts.guest title="Two-step login">
    <p class="text-xs font-semibold tracking-wider text-brand-700 uppercase">Two-step login</p>
    <h1 class="mt-1 text-2xl font-semibold text-stone-900">{{ $recovery ? 'Enter a recovery code' : 'Enter your 6-digit code' }}</h1>
    <p class="mt-1 text-sm text-stone-500">{{ $recovery ? 'Use one of the recovery codes you saved when you switched on two-step login. Each code works once.' : 'Open your authenticator app (Google Authenticator, Microsoft Authenticator, Authy…) and type the code shown for this account.' }}</p>

    <form method="POST" action="{{ route('two-factor.challenge') }}" class="mt-6 space-y-4">
        @csrf
        @if ($recovery)
            <div>
                <label class="label" for="recovery_code">Recovery code</label>
                <input class="input font-mono" id="recovery_code" type="text" name="recovery_code" required autofocus autocomplete="off" placeholder="xxxx-xxxx">
                @error('recovery_code') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        @else
            <div>
                <label class="label" for="code">Code</label>
                <input class="input text-center font-mono text-xl tracking-[0.4em]" id="code" type="text" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" required autofocus autocomplete="one-time-code" placeholder="000000">
                @error('code') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        @endif
        <button class="btn-primary w-full" type="submit">Verify and sign in</button>
    </form>
    <p class="mt-6 text-xs text-stone-500">
        @if ($recovery) <a class="link" href="{{ route('two-factor.challenge') }}">Use the authenticator app instead</a>
        @else Lost your phone? <a class="link" href="{{ route('two-factor.challenge', ['recovery' => 1]) }}">Use a recovery code</a>. No codes either? Ask another Super Admin to reset two-step login for you. @endif
        · <a class="link" href="{{ route('login') }}">Start again</a>
    </p>
</x-layouts.guest>
