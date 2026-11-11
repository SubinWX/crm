<x-layouts.portal title="Sign in">
    <div class="card mx-auto max-w-md p-6 shadow-sm">
        <h1 class="text-xl font-semibold text-stone-900">Sign in to the support portal</h1>
        <p class="mt-1 mb-5 text-sm text-stone-500">View your tickets or raise a new one.</p>
        @include('portal._google')
        <form method="POST" action="{{ route('portal.login') }}" class="space-y-4">
            @csrf
            <div>
                <label class="label" for="email">Email</label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <div class="flex items-center justify-between"><label class="label" for="password">Password</label><a class="link mb-1 text-xs" href="{{ route('portal.password.request') }}">Forgot password?</a></div>
                <input class="input" id="password" type="password" name="password" required autocomplete="current-password">
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-stone-600"><input type="checkbox" name="remember" value="1" class="rounded border-stone-300"> Keep me signed in</label>
            <button class="btn-primary w-full" type="submit">Sign in</button>
        </form>
        <p class="mt-5 text-sm text-stone-600">New here? <a class="link" href="{{ route('portal.register') }}">Create a portal account</a></p>
    </div>
</x-layouts.portal>
