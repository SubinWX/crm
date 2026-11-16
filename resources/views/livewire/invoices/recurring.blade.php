<div>
    <x-page-header title="Recurring Invoices" subtitle="Retainers and maintenance plans that bill themselves. Each one raises a draft invoice on its date, for you to check and send.">
        <a href="{{ route('invoices.index') }}" class="btn-secondary">Invoices</a>
        <button type="button" class="btn-primary" wire:click="edit"><x-icon name="plus" /> New recurring invoice</button>
    </x-page-header>

    <div class="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <x-stat label="Recurring income per month" :value="money($monthly)" hint="Before tax, from the ones switched on" />
        <x-stat label="Per year" :value="money($monthly * 12)" hint="At today’s amounts" />
        <x-stat label="Switched on" :value="$activeCount" :hint="($templates->count() - $activeCount).' paused or finished'" />
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Client</th><th class="num">Amount</th><th>How often</th><th>Next invoice</th><th class="num">Raised</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($templates as $t)
                        <tr wire:key="rec-{{ $t->id }}" @class(['opacity-60' => ! $t->active])>
                            <td><span class="font-medium text-stone-900">{{ $t->name }}</span><p class="text-xs text-stone-500">{{ $t->items->count() }} {{ str('line')->plural($t->items->count()) }}@if ((float) $t->tax_rate > 0) · {{ (float) $t->tax_rate }}% tax @endif</p></td>
                            <td>{{ $t->client?->name ?? '—' }}</td>
                            <td class="num">{{ money($t->amount()) }}</td>
                            <td>{{ \App\Models\RecurringInvoice::FREQUENCIES[$t->frequency] ?? $t->frequency }}</td>
                            <td class="whitespace-nowrap">{{ $t->isFinished() ? '—' : fmt_date($t->next_date) }}@if ($t->end_date) <p class="text-xs text-stone-500">until {{ fmt_date($t->end_date) }}</p>@endif</td>
                            <td class="num">{{ $t->invoices_count }}</td>
                            <td>@if ($t->isFinished()) <span class="badge-gray">Finished</span> @elseif ($t->active) <span class="badge-green">On</span> @else <span class="badge-amber">Paused</span> @endif</td>
                            <td class="text-right whitespace-nowrap">
                                @if ($t->active && ! $t->isFinished()) <button type="button" class="btn-ghost btn-sm" wire:click="generateNow({{ $t->id }})" wire:confirm="Raise the {{ fmt_date($t->next_date) }} invoice for {{ $t->client?->name }} now, as a draft?">Raise now</button> @endif
                                @unless ($t->isFinished()) <button type="button" class="btn-ghost btn-sm" wire:click="toggle({{ $t->id }})">{{ $t->active ? 'Pause' : 'Switch on' }}</button> @endunless
                                <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $t->id }})">Edit</button>
                                <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $t->id }})" wire:confirm="Delete this recurring invoice? Invoices it already raised are kept.">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="8">No recurring invoices yet. Press <strong>New recurring invoice</strong> to set up a retainer or maintenance plan.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-2 text-xs text-stone-500">Invoices are raised as drafts, never sent by themselves. Any that are due are raised when this page is opened.</p>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit recurring invoice' : 'New recurring invoice'" size="max-w-4xl">
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-3">
                    <x-field label="Client" name="form.client_id" required>
                        <select class="input" wire:model="form.client_id"><option value="">Select…</option>@foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach</select>
                    </x-field>
                    <x-field label="Name (for your own list)" name="form.name" required class="md:col-span-2"><input class="input" type="text" wire:model="form.name" placeholder="e.g. Monthly website maintenance"></x-field>
                    <x-field label="How often" name="form.frequency" required>
                        <select class="input" wire:model="form.frequency">@foreach (\App\Models\RecurringInvoice::FREQUENCIES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select>
                    </x-field>
                    <x-field label="Next invoice date" name="form.next_date" required><input class="input" type="date" wire:model="form.next_date"></x-field>
                    <x-field label="Stop after" name="form.end_date" hint="Leave empty to keep going."><input class="input" type="date" wire:model="form.end_date"></x-field>
                    <x-field label="Days to pay" name="form.due_days" required><input class="input" type="number" min="0" wire:model="form.due_days"></x-field>
                    <x-field label="Tax rate %" name="form.tax_rate" required><input class="input" type="number" step="0.01" min="0" wire:model="form.tax_rate"></x-field>
                </div>

                <div>
                    <p class="label">Invoice lines</p>
                    <div class="space-y-2">
                        @foreach ($items as $i => $item)
                            <div class="grid gap-2 sm:grid-cols-[1fr_7rem_6rem_9rem_auto]" wire:key="line-{{ $i }}">
                                <div><input class="input" type="text" wire:model="items.{{ $i }}.description" placeholder="e.g. Website maintenance - {month} {year}" aria-label="Description">@error("items.$i.description") <p class="field-error">{{ $message }}</p> @enderror</div>
                                <input class="input" type="text" wire:model="items.{{ $i }}.hsn_sac" placeholder="HSN/SAC" aria-label="HSN or SAC code">
                                <div><input class="input" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="items.{{ $i }}.quantity" placeholder="Qty" aria-label="Quantity">@error("items.$i.quantity") <p class="field-error">{{ $message }}</p> @enderror</div>
                                <div><input class="input" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="items.{{ $i }}.unit_price" placeholder="Price" aria-label="Price">@error("items.$i.unit_price") <p class="field-error">{{ $message }}</p> @enderror</div>
                                <button type="button" class="btn-ghost" wire:click="removeItem({{ $i }})" aria-label="Remove this line"><x-icon name="trash" /></button>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <button type="button" class="btn-secondary btn-sm" wire:click="addItem"><x-icon name="plus" /> Add line</button>
                        <p class="text-sm text-stone-700">Each invoice, before tax: <strong class="text-stone-900">{{ money($formTotal) }}</strong></p>
                    </div>
                    <p class="mt-1 text-xs text-stone-500">Write <code class="rounded bg-stone-100 px-1">{month}</code> and <code class="rounded bg-stone-100 px-1">{year}</code> in a line and each invoice shows its own month, for example “November 2026”.</p>
                </div>
                <x-field label="Notes printed on the invoice" name="form.notes"><textarea class="input" rows="2" wire:model="form.notes"></textarea></x-field>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
