{{-- "copy" puts text on the clipboard and shows a short confirmation; it falls back to the older method where the new one is not allowed. --}}
<div x-data="{
        async copy(text, what) {
            try { await navigator.clipboard.writeText(text); }
            catch (e) { const t = document.createElement('textarea'); t.value = text; t.style.position = 'fixed'; t.style.opacity = '0'; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove(); }
            window.dispatchEvent(new CustomEvent('toast', { detail: { message: what + ' copied.' } }));
        },
     }">
    <x-page-header title="Bank Details" subtitle="The company’s bank accounts. Copy one detail, or all of them, to paste into an email or message.">
        <button type="button" class="btn-primary" wire:click="edit"><x-icon name="plus" /> Add bank account</button>
    </x-page-header>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
        @forelse ($accounts as $account)
            @php $text = $account->asText(); @endphp
            <section class="card" wire:key="bank-{{ $account->id }}">
                <div class="card-header">
                    <h2 class="card-title">{{ $account->label }} @if ($account->is_default) <span class="badge-green ml-1">Default</span> @endif</h2>
                    <span class="flex gap-1">
                        <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $account->id }})">Edit</button>
                        @if ($canDelete) <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $account->id }})" wire:confirm="Delete the bank details “{{ $account->label }}”? This cannot be undone.">Delete</button> @endif
                    </span>
                </div>
                <dl class="divide-y divide-stone-100 text-sm">
                    @foreach ($account->details() as $label => $value)
                        <div class="flex items-center gap-3 px-4 py-2">
                            <dt class="w-32 shrink-0 text-stone-500">{{ $label }}</dt>
                            <dd @class(['min-w-0 flex-1 break-all text-stone-900', 'font-mono' => in_array($label, ['Account number', 'IFSC code', 'SWIFT code'], true)])>{{ $value }}</dd>
                            <button type="button" class="btn-ghost btn-sm" @click="copy(@js($value), @js($label))" aria-label="Copy {{ $label }}">Copy</button>
                        </div>
                    @endforeach
                </dl>
                @if ($account->notes) <p class="border-t border-stone-100 px-4 py-2 text-xs text-stone-500">{{ $account->notes }}</p> @endif

                <div class="border-t border-stone-200 bg-stone-50 p-4">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <p class="label mb-0">All details, ready to paste</p>
                        <button type="button" class="btn-primary btn-sm" @click="copy(@js($text), 'All details')">Copy all</button>
                    </div>
                    <textarea class="input font-mono text-xs" rows="{{ count($account->details()) }}" readonly @focus="$el.select()" aria-label="All details of {{ $account->label }}">{{ $text }}</textarea>
                </div>
            </section>
        @empty
            <div class="card p-8 text-center text-sm text-stone-500 xl:col-span-2">No bank details yet. Press <strong>Add bank account</strong> to add the first one.</div>
        @endforelse
    </div>
    <p class="mt-3 text-xs text-stone-500">Only Super Admins and Admins can open this page. Account numbers are stored encrypted, and every change is recorded in the activity log.</p>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit bank account' : 'Add bank account'">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <x-field label="Name (for your own list)" name="form.label" required><input class="input" type="text" wire:model="form.label" placeholder="e.g. Main current account"></x-field>
                <x-field label="Account name" name="form.account_name" required hint="The name the account is held in."><input class="input" type="text" wire:model="form.account_name"></x-field>
                <x-field label="Bank" name="form.bank_name" required><input class="input" type="text" wire:model="form.bank_name" placeholder="e.g. HDFC Bank"></x-field>
                <x-field label="Account number" name="form.account_number" required><input class="input font-mono" type="text" inputmode="numeric" autocomplete="off" wire:model="form.account_number"></x-field>
                <x-field label="IFSC code" name="form.ifsc"><input class="input font-mono uppercase" type="text" maxlength="11" autocomplete="off" wire:model="form.ifsc" placeholder="e.g. HDFC0001234"></x-field>
                <x-field label="Branch" name="form.branch"><input class="input" type="text" wire:model="form.branch" placeholder="e.g. Kozhikode Main"></x-field>
                <x-field label="Account type" name="form.account_type" required>
                    <select class="input" wire:model="form.account_type">@foreach (\App\Models\BankAccount::TYPES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select>
                </x-field>
                <x-field label="UPI ID" name="form.upi_id"><input class="input" type="text" autocomplete="off" wire:model="form.upi_id" placeholder="e.g. company@hdfcbank"></x-field>
                <x-field label="SWIFT code" name="form.swift" hint="Only needed for payments from abroad."><input class="input font-mono uppercase" type="text" maxlength="11" autocomplete="off" wire:model="form.swift"></x-field>
                <label class="flex items-center gap-2 self-end pb-2 text-sm text-stone-700"><input type="checkbox" wire:model="form.is_default"> Default account (shown first)</label>
                <x-field label="Notes" name="form.notes" class="md:col-span-2" hint="For your team only; not included when copying."><textarea class="input" rows="2" wire:model="form.notes"></textarea></x-field>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
