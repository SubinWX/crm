<x-layouts.portal title="Confirm your email">
    <div class="card mx-auto max-w-md p-6 text-center shadow-sm">
        <span class="mx-auto grid size-12 place-items-center rounded-full bg-brand-50 text-brand-700"><x-icon name="bell" class="size-6" /></span>
        <h1 class="mt-4 text-xl font-semibold text-stone-900">Confirm your email address</h1>
        <p class="mt-2 text-sm text-stone-600">We sent a confirmation link to <strong>{{ auth('portal')->user()->email }}</strong>. Open it to start submitting tickets. Check your spam folder if it has not arrived.</p>
        @error('email') <p class="field-error mt-3">{{ $message }}</p> @enderror
        <form method="POST" action="{{ route('portal.verify.resend') }}" class="mt-5">
            @csrf
            <button type="submit" class="btn-primary w-full">Send the link again</button>
        </form>
        <p class="mt-4 text-xs text-stone-500">Wrong address? Sign out and create the account again with the right one.</p>
    </div>
</x-layouts.portal>
