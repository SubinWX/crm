<x-layouts.portal title="Create account">
    <div class="card mx-auto max-w-md p-6 shadow-sm">
        <h1 class="text-xl font-semibold text-stone-900">Create your portal account</h1>
        <p class="mt-1 mb-5 text-sm text-stone-500">You will use it to submit tickets and follow our replies.</p>
        @include('portal._google')
        <form method="POST" action="{{ route('portal.register') }}" class="space-y-4">
            @csrf
            {{-- Honeypot for bots: hidden from people and assistive technology --}}
            <div class="hidden" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div>
                <label class="label" for="name">Your name <span class="text-red-600">*</span></label>
                <input class="input" id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
                @error('name') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="email">Email <span class="text-red-600">*</span></label>
                <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="company">Company</label>
                    <input class="input" id="company" type="text" name="company" value="{{ old('company') }}" autocomplete="organization">
                    @error('company') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label" for="phone">Phone</label>
                    <input class="input" id="phone" type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel">
                    @error('phone') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
            <div>
                <label class="label" for="password">Password <span class="text-red-600">*</span></label>
                <input class="input" id="password" type="password" name="password" required autocomplete="new-password">
                <p class="mt-1 text-xs text-stone-500">At least 8 characters.</p>
                @error('password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm password <span class="text-red-600">*</span></label>
                <input class="input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button class="btn-primary w-full" type="submit">Create account</button>
        </form>
        <p class="mt-5 text-sm text-stone-600">Already registered? <a class="link" href="{{ route('portal.login') }}">Sign in</a></p>
    </div>
</x-layouts.portal>
