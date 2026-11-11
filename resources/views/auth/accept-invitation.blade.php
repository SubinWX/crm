<x-layouts.guest title="Accept invitation">
    @if (! $user)
        <h1 class="text-2xl font-semibold text-stone-900">This invitation is no longer valid</h1>
        <p class="mt-2 text-sm text-stone-600">It may have expired or already been used. Ask a Super Admin to resend it.</p>
        <p class="mt-6 text-sm"><a class="link" href="{{ route('login') }}">Go to sign in</a></p>
    @else
        <h1 class="text-2xl font-semibold text-stone-900">Welcome, {{ $user->name }}</h1>
        <p class="mt-1 text-sm text-stone-500">Set a password to activate your {{ $user->role_label }} account for <strong>{{ $user->email }}</strong>.</p>

        <form method="POST" action="{{ route('invitation.accept', $token) }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="label" for="password">Password</label>
                <input class="input" id="password" type="password" name="password" required autofocus autocomplete="new-password">
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm password</label>
                <input class="input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button class="btn-primary w-full" type="submit">Activate account</button>
        </form>
    @endif
</x-layouts.guest>
