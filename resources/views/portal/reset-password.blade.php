<x-layouts.portal title="Choose a new password">
    <div class="card mx-auto max-w-md p-6 shadow-sm">
        <h1 class="mb-5 text-xl font-semibold text-stone-900">Choose a new password</h1>
        <form method="POST" action="{{ route('portal.password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label class="label" for="email">Email</label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="password">New password</label>
                <input class="input" id="password" type="password" name="password" required autocomplete="new-password">
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm password</label>
                <input class="input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button class="btn-primary w-full" type="submit">Update password</button>
        </form>
    </div>
</x-layouts.portal>
