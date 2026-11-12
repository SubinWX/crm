<div>
    <x-page-header :title="$invoice ? 'Edit '.$invoice->number : 'New Invoice'" subtitle="Saved as a draft. Super Admins are reminded on the planned send date if it has not been sent.">
        <a href="{{ $invoice ? route('invoices.show', $invoice) : route('invoices.index') }}" class="btn-secondary">Cancel</a>
    </x-page-header>

    <form wire:submit="save" class="space-y-5">
        <section class="card">
            <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
                <x-field label="Client" name="form.client_id" required class="md:col-span-2">
                    <select class="input" wire:model.live="form.client_id">
                        <option value="">Select client…</option>
                        @foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Recipient" name="form.contact_id" hint="Who the invoice is addressed to. Manage contacts on the client profile." class="md:col-span-2">
                    <select class="input" wire:model="form.contact_id" @disabled($contacts->isEmpty())>
                        <option value="">The client (no named recipient)</option>
                        @foreach ($contacts as $contact) <option value="{{ $contact->id }}">{{ $contact->label() }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Invoice number" name="form.number" required><input class="input" type="text" wire:model="form.number"></x-field>
                <x-field label="Currency" name="form.currency" required>
                    <select class="input" wire:model.live="form.currency">
                        @foreach (\App\Livewire\Settings\Index::CURRENCIES as $code => $label) <option value="{{ $code }}">{{ $code }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Invoice date" name="form.invoice_date" required><input class="input" type="date" wire:model="form.invoice_date"></x-field>
                <x-field label="Planned Send Date" name="form.planned_send_date" hint="Internal reminder to review and send."><input class="input" type="date" wire:model="form.planned_send_date"></x-field>
                <x-field label="Due date" name="form.due_date"><input class="input" type="date" wire:model="form.due_date"></x-field>
                @if (\App\Support\Gst::enabled())
                    <x-field label="Place of supply" name="form.place_of_supply" required hint="The client’s state. Same state as yours = CGST + SGST; another state = IGST.">
                        <select class="input" wire:model.live="form.place_of_supply">
                            <option value="">Select…</option>
                            @foreach (\App\Support\Gst::STATES as $code => $name) <option value="{{ $code }}">{{ $code === \App\Support\Gst::FOREIGN ? $name : $code.' · '.$name }}</option> @endforeach
                        </select>
                    </x-field>
                @endif
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Line items</h2><button type="button" class="btn-secondary btn-sm" wire:click="addItem"><x-icon name="plus" /> Add line</button></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th class="w-full">Description</th>@if (\App\Support\Gst::enabled())<th>HSN / SAC</th>@endif<th>Qty</th><th>Unit price</th><th class="num">Amount</th><th><span class="sr-only">Remove</span></th></tr></thead>
                    <tbody>
                        @foreach ($items as $index => $item)
                            <tr wire:key="item-{{ $index }}">
                                <td class="min-w-72">
                                    <input class="input" type="text" wire:model="items.{{ $index }}.description" aria-label="Description" placeholder="e.g. Website maintenance — November">
                                    @error("items.{$index}.description") <p class="field-error">{{ $message }}</p> @enderror
                                </td>
                                @if (\App\Support\Gst::enabled())
                                    <td>
                                        <input class="input w-28" type="text" inputmode="numeric" maxlength="8" wire:model="items.{{ $index }}.hsn_sac" aria-label="HSN or SAC code" placeholder="998314">
                                        @error("items.{$index}.hsn_sac") <p class="field-error">{{ $message }}</p> @enderror
                                    </td>
                                @endif
                                <td>
                                    <input class="input w-24 text-right" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="items.{{ $index }}.quantity" aria-label="Quantity">
                                    @error("items.{$index}.quantity") <p class="field-error">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <input class="input w-36 text-right" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="items.{{ $index }}.unit_price" aria-label="Unit price">
                                    @error("items.{$index}.unit_price") <p class="field-error">{{ $message }}</p> @enderror
                                </td>
                                <td class="num">{{ money((float) ($item['quantity'] ?: 0) * (float) ($item['unit_price'] ?: 0), $form['currency']) }}</td>
                                <td><button type="button" class="btn-ghost" wire:click="removeItem({{ $index }})" aria-label="Remove line"><x-icon name="x" /></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @error('items') <p class="field-error px-4 pb-3">{{ $message }}</p> @enderror

            <div class="grid gap-6 border-t border-stone-200 p-4 md:grid-cols-2">
                <x-field label="Notes (printed on the invoice)" name="form.notes"><x-editor model="form.notes" :value="$form['notes']" min-height="6rem" label="Notes" /></x-field>
                <div class="space-y-2 text-sm md:ml-auto md:w-80">
                    <div class="flex items-center justify-between"><span class="text-stone-600">Subtotal</span><span class="tabular-nums">{{ money($totals['subtotal'], $form['currency']) }}</span></div>
                    <div class="flex items-center justify-between gap-3">
                        <label for="discount" class="text-stone-600">Discount</label>
                        <input id="discount" class="input w-36 text-right" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="form.discount">
                    </div>
                    @error('form.discount') <p class="field-error text-right">{{ $message }}</p> @enderror
                    <div class="flex items-center justify-between gap-3">
                        <label for="tax" class="text-stone-600">{{ \App\Support\Gst::enabled() ? 'GST %' : 'Tax / GST %' }}</label>
                        <input id="tax" class="input w-36 text-right" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="form.tax_rate">
                    </div>
                    @error('form.tax_rate') <p class="field-error text-right">{{ $message }}</p> @enderror
                    @if ($totals['kind'] === 'intra')
                        <div class="flex items-center justify-between"><span class="text-stone-600">CGST ({{ (float) ($form['tax_rate'] ?: 0) / 2 }}%)</span><span class="tabular-nums">{{ money($totals['cgst'], $form['currency']) }}</span></div>
                        <div class="flex items-center justify-between"><span class="text-stone-600">SGST ({{ (float) ($form['tax_rate'] ?: 0) / 2 }}%)</span><span class="tabular-nums">{{ money($totals['sgst'], $form['currency']) }}</span></div>
                    @elseif ($totals['kind'] === 'inter')
                        <div class="flex items-center justify-between"><span class="text-stone-600">IGST ({{ (float) ($form['tax_rate'] ?: 0) }}%)</span><span class="tabular-nums">{{ money($totals['igst'], $form['currency']) }}</span></div>
                    @else
                        <div class="flex items-center justify-between"><span class="text-stone-600">Tax</span><span class="tabular-nums">{{ money($totals['tax'], $form['currency']) }}</span></div>
                    @endif
                    <div class="flex items-center justify-between border-t border-stone-200 pt-2 text-base font-semibold text-stone-900"><span>Total</span><span class="tabular-nums">{{ money($totals['total'], $form['currency']) }}</span></div>
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ $invoice ? route('invoices.show', $invoice) : route('invoices.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save draft</button>
        </div>
    </form>
</div>
