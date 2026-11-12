<div class="max-w-2xl">
    <x-page-header title="Two-step login" subtitle="After your password, signing in also asks for a 6-digit code from an app on your phone." />

    @if ($forced)
        <div class="mb-5 rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
            <p class="font-semibold">Two-step login is required for Super Admins.</p>
            <p>Set it up below to continue. The rest of the CRM opens as soon as it is done.</p>
        </div>
    @endif

    @if ($newCodes)
        <section class="card mb-5 border-brand-200">
            <div class="card-header"><h2 class="card-title">Save these recovery codes now</h2></div>
            <div class="p-4 sm:p-5">
                <p class="text-sm text-stone-600">If you lose your phone, each code lets you sign in once. They are shown only this one time: print them or keep them in a password manager.</p>
                <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm sm:grid-cols-4" x-ref="codes">
                    @foreach ($newCodes as $c) <li class="rounded bg-stone-100 px-2 py-1.5 text-center text-stone-900">{{ $c }}</li> @endforeach
                </ul>
                <div class="mt-4 flex flex-wrap gap-2" x-data="{ copied: false }">
                    <button type="button" class="btn-secondary" x-on:click="navigator.clipboard.writeText(@js(implode("\n", $newCodes))); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy codes'">Copy codes</button>
                    <a href="{{ route('dashboard') }}" class="btn-primary">I have saved them</a>
                </div>
            </div>
        </section>
    @endif

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Authenticator app</h2>
            <span class="{{ $enabled ? 'badge-green' : 'badge-gray' }}">{{ $enabled ? 'On' : 'Off' }}</span>
        </div>
        <div class="p-4 sm:p-5">
            @if ($enabled)
                <p class="text-sm text-stone-700">Two-step login has been on since {{ fmt_date($user->two_factor_confirmed_at?->timezone(crm_tz())) }}. You have <strong>{{ $codesLeft }}</strong> unused recovery {{ $codesLeft === 1 ? 'code' : 'codes' }}.</p>
                <div class="mt-4 border-t border-stone-200 pt-4">
                    <x-field label="Your current password" name="password" hint="Needed to make new recovery codes or to switch two-step login off.">
                        <input class="input max-w-xs" type="password" wire:model="password" autocomplete="current-password">
                    </x-field>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="btn-secondary" wire:click="regenerate" wire:confirm="Make new recovery codes? The old ones stop working.">Make new recovery codes</button>
                        @unless ($required) <button type="button" class="btn-ghost text-red-700" wire:click="disable" wire:confirm="Switch off two-step login for your account?">Switch off two-step login</button> @endunless
                    </div>
                    @if ($required) <p class="mt-2 text-xs text-stone-500">Required for Super Admins, so it cannot be switched off. If you change phone, ask another Super Admin to reset it from the Users page, then set it up again.</p> @endif
                </div>
            @elseif ($secret)
                <ol class="list-decimal space-y-4 pl-5 text-sm text-stone-700">
                    <li>Install an authenticator app on your phone if you do not have one: Google Authenticator, Microsoft Authenticator or Authy are all free.</li>
                    <li>
                        In the app choose “Add account” and scan this code.
                        <div class="mt-2 flex flex-wrap items-center gap-4">
                            <div class="rounded-md border border-stone-200 bg-white p-2 [&_svg]:size-44">{!! $qr !!}</div>
                            <div class="min-w-0 text-xs text-stone-500">
                                <p>Cannot scan? Type this key into the app instead:</p>
                                <p class="mt-1 rounded bg-stone-100 px-2 py-1.5 font-mono text-sm break-all text-stone-900 select-all">{{ trim(chunk_split($secret, 4, ' ')) }}</p>
                            </div>
                        </div>
                    </li>
                    <li>
                        Type the 6-digit code the app now shows.
                        <form wire:submit="confirm" class="mt-2 flex flex-wrap items-start gap-2">
                            <div><input class="input w-40 text-center font-mono text-lg tracking-[0.3em]" type="text" wire:model="code" inputmode="numeric" maxlength="7" autocomplete="one-time-code" placeholder="000000" aria-label="6-digit code">@error('code') <p class="field-error max-w-xs">{{ $message }}</p> @enderror</div>
                            <button type="submit" class="btn-primary">Switch on</button>
                            @unless ($forced) <button type="button" class="btn-secondary" wire:click="cancel">Cancel</button> @endunless
                        </form>
                    </li>
                </ol>
            @else
                <p class="text-sm text-stone-700">Your account is protected by your password only. With two-step login, someone who learns your password still cannot sign in without your phone.</p>
                <button type="button" class="btn-primary mt-4" wire:click="start">Set up two-step login</button>
            @endif
        </div>
    </section>
</div>
