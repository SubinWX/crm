<x-layouts.guest title="Reset password">
    <h1 class="text-2xl font-semibold text-stone-900">Reset your password</h1>
    <p class="mt-1 text-sm text-stone-500">Enter your email and we will send a reset link.</p>

    @if (session('status'))
        <div class="mt-4 rounded-md border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label class="label" for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button class="btn-primary w-full" type="submit">Email reset link</button>
    </form>
    <p class="mt-6 text-sm"><a class="link" href="{{ route('login') }}">Back to sign in</a></p>
</x-layouts.guest>
