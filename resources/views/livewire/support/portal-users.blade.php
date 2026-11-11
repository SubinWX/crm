<div>
    <x-page-header title="Portal Users" :subtitle="auth()->user()->isSubAdmin() ? 'Support portal accounts you have added.' : 'Everyone with a login to the support portal. Separate from team accounts.'">
        <button type="button" class="btn-primary" wire:click="create"><x-icon name="plus" /> Add portal user</button>
    </x-page-header>

    <div class="card mb-5 flex flex-wrap items-center gap-3 p-4" x-data="{ copied: false }">
        <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Support portal link</p>
        <a href="{{ route('portal.home') }}" target="_blank" rel="noopener" class="link break-all text-sm" x-ref="portal">{{ route('portal.home') }}</a>
        <button type="button" class="btn-secondary btn-sm" @click="navigator.clipboard.writeText($refs.portal.textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)">
            <span x-show="!copied">Copy link</span><span x-show="copied" x-cloak>Copied</span>
        </button>
        <span class="text-xs text-stone-500">Use this for the “Support” item in your website menu or footer.</span>
    </div>

    @if ($created)
        <div class="card mb-5 flex items-start justify-between gap-3 border-brand-200 bg-brand-50 p-4" role="status">
            <div class="text-sm text-brand-800">
                <p class="font-semibold">Portal account ready for {{ $created['name'] }}</p>
                <p class="mt-0.5">They can sign in at <span class="font-mono text-xs">{{ $created['login_url'] }}</span> with <strong>{{ $created['email'] }}</strong> and the password you set. Share the password privately; it is not shown again.</p>
            </div>
            <button type="button" class="btn-ghost" wire:click="dismissCreated" aria-label="Dismiss"><x-icon name="x" /></button>
        </div>
    @endif

    <div class="card">
        <div class="border-b border-stone-200 p-4">
            <div class="relative w-full md:w-80">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
                <input type="search" class="input pl-9" placeholder="Search name, email, company…" wire:model.live.debounce.300ms="search" aria-label="Search portal users">
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Company / client</th><th>Signs in with</th><th>Tickets</th><th>Added</th><th>Status</th><th>Last sign-in</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr wire:key="pu-{{ $user->id }}">
                            <td class="font-medium text-stone-900">{{ $user->name }}</td>
                            <td>{{ $user->email }}@if ($user->phone)<div class="text-xs text-stone-500">{{ $user->phone }}</div>@endif</td>
                            <td>{{ $user->client?->name ?? $user->company ?? '—' }}@if ($user->client) <span class="badge-green">Client</span>@endif</td>
                            <td class="whitespace-nowrap">{{ $user->signInMethod() }}</td>
                            <td>
                                @if ($canSeeTickets && $user->tickets_count) <a class="link" href="{{ route('support.tickets.index', ['search' => $user->email, 'status' => 'all']) }}">{{ $user->tickets_count }}</a>
                                @else {{ $user->tickets_count }} @endif
                            </td>
                            <td class="whitespace-nowrap">{{ $user->creator ? 'By '.$user->creator->name : 'Self sign-up' }}<div class="text-xs text-stone-500">{{ fmt_date($user->created_at->timezone(crm_tz())) }}</div></td>
                            <td>@if (! $user->is_active) <span class="badge-gray">Disabled</span> @elseif (! $user->email_verified_at) <span class="badge-amber">Email not confirmed</span> @else <span class="badge-green">Active</span> @endif</td>
                            <td class="whitespace-nowrap">{{ fmt_datetime($user->last_login_at, 'Never') }}</td>
                            <td class="text-right whitespace-nowrap">
                                <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $user->id }})">Edit</button>
                                <button type="button" class="btn-ghost btn-sm" wire:click="toggleActive({{ $user->id }})"
                                        wire:confirm="{{ $user->is_active ? 'Disable '.$user->name.'? They will not be able to sign in. Their tickets are kept.' : 'Let '.$user->name.' sign in again?' }}">{{ $user->is_active ? 'Disable' : 'Enable' }}</button>
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="9">No portal users yet. They appear here when someone signs up on the portal, or when you add one.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $users->links() }}</div> @endif
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit portal user' : 'Add portal user'" size="max-w-xl">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <x-field label="Name" name="form.name" required><input class="input" type="text" wire:model="form.name"></x-field>
                <x-field label="Email" name="form.email" required><input class="input" type="email" wire:model="form.email"></x-field>
                <x-field label="Phone" name="form.phone"><input class="input" type="tel" wire:model="form.phone"></x-field>
                <x-field label="Company" name="form.company"><input class="input" type="text" wire:model="form.company"></x-field>
                <x-field label="CRM client" name="form.client_id" hint="Optional. Their tickets are then linked to this client." class="md:col-span-2">
                    <select class="input" wire:model="form.client_id">
                        <option value="">Not linked</option>
                        @foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
                    </select>
                </x-field>
                <x-field :label="$editingId ? 'Set a new password' : 'Password'" name="form.password" :required="! $editingId" class="md:col-span-2"
                         :hint="$editingId ? 'Leave empty to keep their current password.' : 'At least 8 characters. They can change it later with “Forgot password”.'">
                    <div x-data="{ show: false }" class="flex gap-2">
                        <input class="input" :type="show ? 'text' : 'password'" wire:model="form.password" autocomplete="new-password">
                        <button type="button" class="btn-secondary" @click="show = !show" x-text="show ? 'Hide' : 'Show'">Show</button>
                    </div>
                </x-field>
                <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" class="rounded border-stone-300" wire:model="form.is_active"> Account can sign in</label>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">{{ $editingId ? 'Save changes' : 'Create portal user' }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
