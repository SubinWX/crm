<div>
    @if ($staffScope)
        <x-page-header title="Staff" subtitle="Add staff accounts and choose which parts of the CRM each one can use.">
            <button type="button" class="btn-primary" wire:click="create"><x-icon name="plus" /> Add staff</button>
        </x-page-header>
    @else
        <x-page-header title="Admins" subtitle="Super Admin and Admin accounts, and which areas each Admin can use. Staff accounts have their own page.">
            <a href="{{ route('staff.index') }}" class="btn-secondary">Staff accounts ({{ $counts['staff'] }}) →</a>
            <button type="button" class="btn-primary" wire:click="create"><x-icon name="plus" /> Add admin</button>
        </x-page-header>
    @endif

    {{-- Login links: Super Admins and staff have separate sign-in pages --}}
    <div class="card mb-5 divide-y divide-stone-200">
        @foreach (array_filter([$staffScope ? null : ['Admin login link', route('login'), 'Super Admins and Admins sign in here.', 'admin'], $staffScope ? ['Staff login link', route('staff.login'), 'Staff sign in here.', 'staff'] : null]) as [$label, $url, $hint, $for])
            <div class="flex flex-wrap items-center gap-3 p-4" x-data="{ copied: false }">
                <p class="w-36 text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $label }}</p>
                <a href="{{ $url }}" class="link break-all text-sm">{{ $url }}</a>
                <button type="button" class="btn-secondary btn-sm" @click="navigator.clipboard.writeText(@js($url)); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-show="!copied">Copy link</span><span x-show="copied" x-cloak>Copied</span>
                </button>
                <span class="text-xs text-stone-500">{{ $hint }}</span>
            </div>
        @endforeach
    </div>

    @if ($created)
        <div class="card mb-5 border-brand-200 bg-brand-50 p-4" role="status" x-data="{ copied: '' }">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-semibold text-brand-800">{{ $created['invite_url'] ? 'Invitation sent to' : 'Account ready for' }} {{ $created['name'] }} ({{ $created['role'] }})</p>
                    <p class="mt-0.5 text-sm text-brand-800">
                        @if ($created['invite_url']) They set their own password from the invitation link, which is valid for 7 days and is shown only this once.
                        @else They can sign in now with <strong>{{ $created['email'] }}</strong> and the password you set. Share the password with them privately; it is not shown again. @endif
                    </p>
                </div>
                <button type="button" class="btn-ghost" wire:click="dismissCreated" aria-label="Dismiss"><x-icon name="x" /></button>
            </div>
            <dl class="mt-3 space-y-2 text-sm">
                @foreach (array_filter(['Invitation link' => $created['invite_url'], 'Login link' => $created['login_url']]) as $label => $url)
                    <div class="flex flex-wrap items-center gap-2">
                        <dt class="w-28 text-xs font-semibold tracking-wide text-stone-600 uppercase">{{ $label }}</dt>
                        <dd class="min-w-0 flex-1 break-all rounded-md border border-brand-200 bg-white px-2 py-1 font-mono text-xs text-stone-800">{{ $url }}</dd>
                        <button type="button" class="btn-secondary btn-sm" @click="navigator.clipboard.writeText(@js($url)); copied = @js($label); setTimeout(() => copied = '', 2000)">
                            <span x-show="copied !== @js($label)">Copy</span><span x-show="copied === @js($label)" x-cloak>Copied</span>
                        </button>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    @php $requireTwoFactor = setting('require_two_factor') === '1'; @endphp
    @unless ($staffScope)
    <div class="card mb-5 flex flex-wrap items-center justify-between gap-3 p-4">
        <div class="min-w-64 flex-1 text-sm">
            <p class="font-semibold text-stone-900">Two-step login for Super Admins <span class="{{ $requireTwoFactor ? 'badge-green' : 'badge-gray' }} ml-1">{{ $requireTwoFactor ? 'Required' : 'Optional' }}</span></p>
            <p class="text-stone-600">After the password, signing in asks for a code from an authenticator app. Anyone can switch it on for their own account from the top-right menu; when required, every Super Admin must set it up before they can use the CRM.</p>
        </div>
        @if ($requireTwoFactor)
            <button type="button" class="btn-secondary" wire:click="setRequireTwoFactor(false)" wire:confirm="Make two-step login optional for Super Admins?">Make optional</button>
        @else
            <button type="button" class="btn-primary" wire:click="setRequireTwoFactor(true)" wire:confirm="Require two-step login for every Super Admin? Those who have not set it up will be asked to at their next page.">Require it</button>
        @endif
    </div>
    @endunless

    <div @class(['mb-5 flex gap-6 border-b border-stone-200', 'hidden' => $staffScope]) role="tablist">
        @foreach ($roleTabs as $key => $label)
            <button type="button" role="tab" @class(['tab', 'tab-active' => $role === $key]) wire:click="$set('role', '{{ $key }}')">{{ $label }} <span class="text-stone-400">({{ $counts[$key] }})</span></button>
        @endforeach
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Primary phone</th><th>Alternate phone</th>@if ($role !== 'super_admin')<th>Access</th>@endif<th>Equipment</th><th>Status</th><th>Last sign-in</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr wire:key="user-{{ $user->id }}">
                            <td class="font-medium text-stone-900">
                                <div class="flex items-center gap-3">
                                    <x-avatar :src="$user->avatarUrl()" :name="$user->name" />
                                    <span>{{ $user->name }} @if ($user->id === auth()->id()) <span class="text-xs font-normal text-stone-500">(you)</span> @endif</span>
                                </div>
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->phone ?: '—' }}</td>
                            <td>{{ $user->alt_phone ?: '—' }}</td>
                            @if ($role !== 'super_admin')
                                <td><div class="flex max-w-72 flex-wrap gap-1">
                                    @if ($role === 'sub_admin') <span class="badge-gray">Own clients</span> @endif
                                    @forelse (array_intersect(\App\Models\User::grantable($role), $user->permissions ?? []) as $p) <span class="badge-gray" title="{{ \App\Models\User::STAFF_PERMISSIONS[$p] }}">{{ str($p)->replace('.', ': ')->ucfirst() }}</span> @empty @if ($role === 'staff') <span class="text-stone-400">None</span> @endif @endforelse
                                </div></td>
                            @endif
                            <td><a class="link" href="{{ route('equipment.index', ['user' => $user->id]) }}">{{ $user->equipment_count }} {{ str('item')->plural($user->equipment_count) }}</a></td>
                            <td>
                                @if (! $user->is_active) <span class="badge-gray">Inactive</span>
                                @elseif ($user->hasPendingInvitation()) <span class="badge-amber">Invitation pending</span>
                                @else <span class="badge-green">Active</span> @endif
                                @if ($user->hasTwoFactor()) <span class="badge-blue" title="Two-step login is on">2-step</span> @endif
                            </td>
                            <td class="whitespace-nowrap">{{ fmt_datetime($user->last_login_at, 'Never') }}</td>
                            <td class="text-right whitespace-nowrap">
                                @if ($user->hasPendingInvitation() && $user->is_active)
                                    <button type="button" class="btn-ghost btn-sm" wire:click="resend({{ $user->id }})">Resend invite</button>
                                @endif
                                <a href="{{ $user->id === auth()->id() ? route('profile') : route('profile.show', $user) }}" class="btn-ghost btn-sm">Profile</a>
                                <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $user->id }})">Edit</button>
                                @if ($user->hasTwoFactor() && $user->id !== auth()->id())
                                    <button type="button" class="btn-ghost btn-sm" wire:click="resetTwoFactor({{ $user->id }})" wire:confirm="Reset two-step login for {{ $user->name }}? Use this when they have lost their phone. They sign in with the password only until they set it up again.">Reset 2-step</button>
                                @endif
                                @if ($user->id !== auth()->id())
                                    <button type="button" class="btn-ghost btn-sm" wire:click="toggleActive({{ $user->id }})"
                                            wire:confirm="{{ $user->is_active ? 'Deactivate '.$user->name.'? They will be signed out and lose access. Their records stay intact.' : 'Reactivate '.$user->name.'?' }}">
                                        {{ $user->is_active ? 'Deactivate' : 'Reactivate' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="$role !== 'super_admin' ? 9 : 8">No {{ \App\Models\User::ROLES[$role] ?? '' }} accounts yet.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit user' : 'Add user'" size="max-w-xl">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <x-field label="Headshot" name="photo" hint="JPG, PNG or WebP. After choosing a picture you can crop it before it is saved." class="md:col-span-2">
                    @php $editingUser = $editingId ? $users->firstWhere('id', $editingId) : null; @endphp
                    <div class="flex flex-wrap items-center gap-4">
                        @if ($photo && ! $errors->has('photo'))
                            <img src="{{ $photo->temporaryUrl() }}" alt="New headshot preview" class="size-16 rounded-full border border-stone-200 object-cover">
                        @else
                            <x-avatar :src="$removePhoto ? null : $editingUser?->avatarUrl()" :name="$form['name']" size="size-16" class="text-base" />
                        @endif
                        <div class="min-w-56 flex-1">
                            <x-photo-cropper model="photo" label="Headshot" />
                        </div>
                        @if ($editingUser?->avatar_path && ! $photo)
                            <label class="flex items-center gap-2 text-sm text-stone-600"><input type="checkbox" class="rounded border-stone-300" wire:model.live="removePhoto"> Remove photo</label>
                        @endif
                    </div>
                </x-field>
                <x-field label="Name" name="form.name" required><input class="input" type="text" wire:model="form.name"></x-field>
                <x-field label="Email" name="form.email" required><input class="input" type="email" wire:model="form.email"></x-field>
                <x-field label="Primary phone" name="form.phone"><input class="input" type="tel" wire:model="form.phone"></x-field>
                <x-field label="Alternate phone" name="form.alt_phone"><input class="input" type="tel" wire:model="form.alt_phone"></x-field>
                <x-field label="Date of birth" name="form.date_of_birth" hint="Optional. Their birthday shows on the Calendar each year; the year is never displayed."><input class="input" type="date" wire:model="form.date_of_birth" max="{{ today_local()->subDay()->toDateString() }}"></x-field>
                @if ($editingId !== auth()->id())
                    <x-field :label="$editingId ? 'Set a new password' : 'Password'" name="form.password" class="md:col-span-2"
                             :hint="$editingId ? 'Leave empty to keep their current password. At least 8 characters.' : 'At least 8 characters. Leave empty to email an invitation so they choose their own.'">
                        <div x-data="{ show: false }" class="flex gap-2">
                            <input class="input" :type="show ? 'text' : 'password'" wire:model="form.password" autocomplete="new-password">
                            <button type="button" class="btn-secondary" @click="show = !show" x-text="show ? 'Hide' : 'Show'">Show</button>
                        </div>
                    </x-field>
                @endif
                @if ($staffScope)
                    <x-field label="Role"><p class="py-2 text-sm text-stone-700">Staff <span class="text-stone-500">· signs in on the staff page</span></p></x-field>
                @else
                    <x-field label="Role" name="form.role" required>
                        <select class="input" wire:model.live="form.role">
                            @foreach ($roleTabs as $key => $label) <option value="{{ $key }}">{{ \App\Models\User::ROLES[$key] }}</option> @endforeach
                        </select>
                    </x-field>
                @endif
                <x-field label="Active status" name="form.is_active">
                    <label class="flex items-center gap-2 py-2 text-sm"><input type="checkbox" class="rounded border-stone-300" wire:model="form.is_active"> Account is active</label>
                </x-field>
                @php $grantable = \App\Models\User::grantable($form['role']); @endphp
                @if ($form['role'] === 'super_admin')
                    <p class="rounded-md bg-stone-50 p-3 text-sm text-stone-600 md:col-span-2">Super Admins have access to everything: users, settings, finances and all records. They receive every internal notification, so there is nothing to tick here.</p>
                @else
                    <div class="md:col-span-2" wire:key="access-{{ $form['role'] }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="label mb-0">Access: what this person can use</p>
                            <span class="flex gap-3 text-xs">
                                <button type="button" class="link" wire:click="$set('form.permissions', {{ json_encode(array_values(array_unique(array_merge($mayGive, array_diff($form['permissions'], $mayGive))))) }})">Tick all</button>
                                <button type="button" class="link" wire:click="$set('form.permissions', {{ json_encode(array_values(array_diff($form['permissions'], $mayGive))) }})">Clear all</button>
                            </span>
                        </div>
                        @if ($form['role'] === 'sub_admin')
                            <p class="mt-1 rounded-md bg-stone-50 p-3 text-sm text-stone-600">Admins always can add clients, see and edit only the clients they added, and add support portal users. You can also open the areas below for them. <strong class="text-stone-800">Those areas show every record in them, not only this person’s clients.</strong></p>
                        @endif
                        <div class="mt-2 grid gap-x-6 gap-y-3 rounded-md border border-stone-200 p-3 sm:grid-cols-2">
                            @foreach (\App\Models\User::PERMISSION_GROUPS as $group => $keys)
                                @if ($shown = array_values(array_intersect($keys, $grantable)))
                                    <fieldset>
                                        <legend class="text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $group }}</legend>
                                        <div class="mt-1 space-y-1.5">
                                            @foreach ($shown as $key)
                                                @php $locked = ! in_array($key, $mayGive, true); @endphp
                                                <label @class(['flex items-start gap-2 text-sm', 'text-stone-400' => $locked]) @if ($locked) title="Only a Super Admin can change this, because this area has not been given to you." @endif>
                                                    <input type="checkbox" class="mt-0.5 rounded border-stone-300 disabled:opacity-50" value="{{ $key }}" wire:model="form.permissions" @disabled($locked)>
                                                    <span>{{ \App\Models\User::STAFF_PERMISSIONS[$key] }}@if ($locked) <span class="text-xs">(Super Admin only)</span>@endif</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endif
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-stone-500">{{ $form['role'] === 'staff' ? 'Tasks, posters, the calendar and their own equipment are visible to all staff.' : '' }} Users, settings and the activity log are always Super Admin only.</p>
                        @error('form.permissions') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="photo,save">{{ $editingId ? 'Save changes' : 'Create user' }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
