@php $staff = ($area ?? 'admin') === 'staff'; @endphp
<x-layouts.guest :title="$staff ? 'Staff sign in' : 'Admin sign in'">
    <p class="text-xs font-semibold tracking-wider text-brand-700 uppercase">{{ $staff ? 'Staff' : 'Admin' }}</p>
    <h1 class="mt-1 text-2xl font-semibold text-stone-900">{{ $staff ? 'Staff sign in' : 'Admin sign in' }}</h1>
    <p class="mt-1 text-sm text-stone-500">{{ $staff ? 'For Staff accounts.' : 'For Super Admin and Sub Admin accounts.' }}</p>

    @if (session('status'))
        <div class="mt-4 rounded-md border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-800">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ $staff ? route('staff.login') : route('login') }}" class="mt-6 space-y-4">
        @csrf
        <div>
            <label class="label" for="email">Email</label>
            <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email') <p class="field-error break-words">{{ $message }}</p> @enderror
        </div>
        <div>
            <div class="flex items-center justify-between">
                <label class="label" for="password">Password</label>
                <a class="link mb-1 text-xs" href="{{ route('password.request') }}">Forgot password?</a>
            </div>
            <input class="input" id="password" type="password" name="password" required autocomplete="current-password">
            @error('password') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm text-stone-600">
            <input type="checkbox" name="remember" value="1" class="rounded border-stone-300 text-brand-600"> Keep me signed in
        </label>
        <button class="btn-primary w-full" type="submit">Sign in</button>
    </form>
    <p class="mt-6 text-xs text-stone-500">
        @if ($staff) Super Admin or Sub Admin? <a class="link" href="{{ route('login') }}">Use the admin sign-in page</a>.
        @else Staff? <a class="link" href="{{ route('staff.login') }}">Use the staff sign-in page</a>. @endif
        No account? Ask a Super Admin to add you.
    </p>
</x-layouts.guest>
