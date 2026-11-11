<div>
    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Contacts</h2>
            @can('client.update', $client) <button type="button" class="btn-primary btn-sm" wire:click="create"><x-icon name="plus" /> Add contact</button> @endcan
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Used for</th><th>Email</th><th>Phone</th><th>Notes</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($contacts as $contact)
                        <tr wire:key="contact-{{ $contact->id }}">
                            <td class="font-medium text-stone-900">{{ $contact->name }} @if ($contact->is_primary) <span class="badge-green">Primary</span> @endif</td>
                            <td>{{ \App\Models\ClientContact::ROLES[$contact->role] ?? $contact->role }}</td>
                            <td>{{ $contact->email ?: '—' }}</td>
                            <td class="whitespace-nowrap">{{ $contact->phone ?: '—' }}</td>
                            <td class="max-w-64 truncate">{{ $contact->notes ?: '—' }}</td>
                            <td class="text-right whitespace-nowrap">
                                @can('client.update', $client)
                                    @unless ($contact->is_primary) <button type="button" class="btn-ghost btn-sm" wire:click="setPrimary({{ $contact->id }})">Make primary</button> @endunless
                                    <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $contact->id }})">Edit</button>
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $contact->id }})" wire:confirm="Remove {{ $contact->name }}?">Remove</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="6">No contacts yet. Add the people you deal with for billing, approvals and day-to-day work.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit contact' : 'Add contact'" size="max-w-xl">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <x-field label="Name" name="form.name" required><input class="input" type="text" wire:model="form.name"></x-field>
                <x-field label="Used for" name="form.role" required>
                    <select class="input" wire:model="form.role">@foreach (\App\Models\ClientContact::ROLES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select>
                </x-field>
                <x-field label="Email" name="form.email"><input class="input" type="email" wire:model="form.email"></x-field>
                <x-field label="Phone" name="form.phone"><input class="input" type="tel" wire:model="form.phone"></x-field>
                <x-field label="Notes" name="form.notes" class="md:col-span-2"><input class="input" type="text" wire:model="form.notes" placeholder="e.g. Approves designs; prefers WhatsApp"></x-field>
                <label class="flex items-center gap-2 text-sm md:col-span-2"><input type="checkbox" class="rounded border-stone-300" wire:model="form.is_primary"> Primary contact for this client</label>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Save contact</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
