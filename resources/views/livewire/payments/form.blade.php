<div>
    <div class="mb-1 text-sm"><a href="{{ route('payments.index') }}" class="link">← Payments</a></div>
    <x-page-header :title="$payment ? 'Payment · '.money($payment->amount) : 'Record Payment'" subtitle="Apply the payment to invoices now, or leave it unallocated and apply it later.">
        @if ($payment)
            <button type="button" class="btn-danger" wire:click="delete" wire:confirm="Delete this payment? Invoice balances will be restored.">Delete payment</button>
        @endif
    </x-page-header>

    <form wire:submit="save" class="space-y-5">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Payment details</h2></div>
            <div class="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-3">
                <x-field label="Client" name="form.client_id" required>
                    <select class="input" wire:model.live="form.client_id">
                        <option value="">Select client…</option>
                        @foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Amount received ({{ setting('currency', 'INR') }})" name="form.amount" required><input class="input" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="form.amount"></x-field>
                <x-field label="Payment date" name="form.payment_date" required><input class="input" type="date" wire:model="form.payment_date"></x-field>
                <x-field label="Payment type" name="form.payment_method_id" required>
                    <select class="input" wire:model="form.payment_method_id">
                        <option value="">Select…</option>
                        @foreach ($methods as $m) <option value="{{ $m->id }}">{{ $m->name }}{{ $m->is_active ? '' : ' (inactive)' }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Payment To" name="form.received_by" required hint="The Super Admin who received the money.">
                    <select class="input" wire:model="form.received_by">
                        <option value="">Select Super Admin…</option>
                        @foreach ($superAdmins as $u) <option value="{{ $u->id }}">{{ $u->name }}{{ $u->is_active ? '' : ' (inactive)' }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Transaction reference" name="form.reference"><input class="input" type="text" wire:model="form.reference" placeholder="UTR, cheque no., gateway ID…"></x-field>
                <x-field label="Notes" name="form.notes" class="md:col-span-2 xl:col-span-3"><input class="input" type="text" wire:model="form.notes"></x-field>
            </div>
        </section>

        <section class="card">
            <div class="card-header">
                <h2 class="card-title">Apply to invoices</h2>
                <div class="flex gap-2">
                    <button type="button" class="btn-secondary btn-sm" wire:click="autoAllocate" @disabled($invoices->isEmpty())>Apply to oldest first</button>
                    <button type="button" class="btn-ghost btn-sm" wire:click="clearAllocations">Clear</button>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Invoice</th><th>Date</th><th>Due</th><th>Status</th><th class="num">Total</th><th class="num">Balance</th><th class="num">Apply now</th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr wire:key="alloc-{{ $invoice->id }}">
                                <td><a class="link" href="{{ route('invoices.show', $invoice) }}" target="_blank" rel="noopener">{{ $invoice->number }}</a></td>
                                <td class="whitespace-nowrap">{{ fmt_date($invoice->invoice_date) }}</td>
                                <td class="whitespace-nowrap">{{ fmt_date($invoice->due_date) }}</td>
                                <td><x-status :value="$invoice->display_status" :label="$invoice->display_status_label" /></td>
                                <td class="num">{{ money($invoice->total, $invoice->currency) }}</td>
                                <td class="num">{{ money($invoice->available, $invoice->currency) }}</td>
                                <td class="num">
                                    <input class="input ml-auto w-36 text-right" type="number" step="0.01" min="0" max="{{ $invoice->available }}"
                                           wire:model.live.debounce.400ms="allocations.{{ $invoice->id }}" aria-label="Amount to apply to {{ $invoice->number }}">
                                </td>
                            </tr>
                        @empty
                            <x-empty colspan="7">{{ $form['client_id'] ? 'This client has no sent invoices with a balance. The payment will be kept as unallocated.' : 'Select a client to see their open invoices.' }}</x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-x-8 gap-y-1 border-t border-stone-200 px-4 py-3 text-sm">
                <span>Payment <strong class="tabular-nums">{{ money((float) ($form['amount'] ?: 0)) }}</strong></span>
                <span>Applied <strong class="tabular-nums">{{ money($allocated) }}</strong></span>
                @php $left = round((float) ($form['amount'] ?: 0) - $allocated, 2); @endphp
                <span @class(['font-semibold', 'text-red-700' => $left < 0, 'text-amber-700' => $left > 0])>Unallocated <span class="tabular-nums">{{ money($left) }}</span></span>
            </div>
            @error('allocations') <p class="field-error px-4 pb-3 text-right">{{ $message }}</p> @enderror
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route("payments.index") }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">{{ $payment ? 'Save payment' : 'Record payment' }}</button>
        </div>
    </form>
</div>
