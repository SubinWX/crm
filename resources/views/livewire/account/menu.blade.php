<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" class="flex cursor-pointer items-center gap-2.5 rounded-full py-1 pr-2 pl-1 hover:bg-stone-100"
            @click="open = !open" :aria-expanded="open" aria-haspopup="true" aria-label="Account menu for {{ $user->name }}">
        <x-avatar :src="$user->avatarUrl()" :name="$user->name" />
        <span class="hidden text-sm font-semibold text-stone-900 sm:inline">{{ str($user->name)->before(' ') }}</span>
        <span class="hidden text-sm text-stone-500 md:inline">({{ $user->role_label }})</span>
    </button>

    <div x-show="open" x-cloak x-transition.opacity class="card absolute right-0 z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] shadow-xl">
        <div class="flex items-center gap-4 border-b border-stone-200 p-5">
            <x-avatar :src="$user->avatarUrl()" :name="$user->name" size="size-16" class="text-lg" />
            <div class="min-w-0">
                <p class="truncate text-base font-semibold text-stone-900">{{ $user->name }}</p>
                <p class="truncate text-sm text-stone-500">{{ $user->email }}</p>
                <p class="mt-0.5 text-xs text-stone-500">{{ $user->role_label }}</p>
            </div>
        </div>
        <div class="p-2">
            <button type="button" class="flex w-full cursor-pointer items-center gap-3 rounded-md px-3 py-2.5 text-left text-sm text-stone-700 hover:bg-stone-50"
                    @click="open = false" wire:click="open('password')">
                <x-icon name="shield" class="size-5 text-stone-400" /> Change Password
            </button>
            <button type="button" class="flex w-full cursor-pointer items-center gap-3 rounded-md px-3 py-2.5 text-left text-sm text-stone-700 hover:bg-stone-50"
                    @click="open = false" wire:click="open('picture')">
                <x-icon name="photo" class="size-5 text-stone-400" /> Change Picture
            </button>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="border-t border-stone-200 p-4">
            @csrf
            <button type="submit" class="btn-secondary rounded-full px-6">Logout</button>
        </form>
    </div>

    {{-- Rendered at the end of <body>: the header is its own layer, so a dialog left inside it would be clipped to the header bar. --}}
    @teleport('body')
        <div>
    @if ($modal === 'password')
        <x-modal title="Change Password" size="max-w-md">
            <form wire:submit="savePassword" class="space-y-4">
                <x-field label="Current password" name="current_password" required>
                    <input class="input" type="password" wire:model="current_password" autocomplete="current-password" autofocus>
                </x-field>
                <x-field label="New password" name="password" required hint="At least 8 characters.">
                    <input class="input" type="password" wire:model="password" autocomplete="new-password">
                </x-field>
                <x-field label="Confirm new password" name="password_confirmation" required>
                    <input class="input" type="password" wire:model="password_confirmation" autocomplete="new-password">
                </x-field>
                <p class="text-xs text-stone-500">You stay signed in here; other devices signed in to this account are signed out.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Change password</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($modal === 'picture')
        <x-modal title="Change Picture" size="max-w-md">
            <form wire:submit="savePicture" class="space-y-4">
                <div class="flex items-center gap-4">
                    @if ($photo && ! $errors->has('photo'))
                        <img src="{{ $photo->temporaryUrl() }}" alt="New picture preview" class="size-20 rounded-full border border-stone-200 object-cover">
                    @else
                        <x-avatar :src="$user->avatarUrl()" :name="$user->name" size="size-20" class="text-xl" />
                    @endif
                    <p class="text-sm text-stone-600">JPG, PNG or WebP, up to 2 MB.<br>A square photo works best.</p>
                </div>
                <x-field name="photo">
                    <input type="file" class="input" accept="image/png,image/jpeg,image/webp" wire:model="photo" aria-label="Profile picture">
                    <p wire:loading wire:target="photo" class="mt-1 text-xs text-stone-500">Uploading…</p>
                </x-field>
                <div class="flex items-center justify-between gap-2">
                    @if ($user->avatar_path)
                        <button type="button" class="btn-ghost text-red-700" wire:click="removePicture" wire:confirm="Remove your profile picture?">Remove picture</button>
                    @else <span></span> @endif
                    <div class="flex gap-2">
                        <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="photo,savePicture">Save picture</button>
                    </div>
                </div>
            </form>
        </x-modal>
    @endif
        </div>
    @endteleport
</div>
