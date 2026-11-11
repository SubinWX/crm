<x-layouts.portal title="Reset password">
    <div class="card mx-auto max-w-md p-6 shadow-sm">
        <h1 class="text-xl font-semibold text-stone-900">Reset your password</h1>
        <p class="mt-1 mb-5 text-sm text-stone-500">Enter your email and we will send a reset link.</p>
        <form method="POST" action="{{ route('portal.password.email') }}" class="space-y-4">
            @csrf
            <div>
                <label class="label" for="email">Email</label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <button class="btn-primary w-full" type="submit">Email reset link</button>
        </form>
        <p class="mt-5 text-sm"><a class="link" href="{{ route('portal.login') }}">Back to sign in</a></p>
    </div>
</x-layouts.portal>
