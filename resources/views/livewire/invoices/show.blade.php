<div>
    <div class="mb-1 text-sm"><a href="{{ route('invoices.index') }}" class="link">← Invoices</a></div>
    <x-page-header :title="'Invoice '.$invoice->number">
        <x-status :value="$invoice->display_status" :label="$invoice->display_status_label" />
        @if ($invoice->status === 'draft') <a href="{{ route('invoices.edit', $invoice) }}" class="btn-secondary">Edit draft</a> @endif
        <a href="{{ route('invoices.pdf', ['invoice' => $invoice, 'inline' => 1]) }}" target="_blank" rel="noopener" class="btn-secondary">Preview PDF</a>
        <a href="{{ route('invoices.pdf', $invoice) }}" class="btn-secondary"><x-icon name="download" /> Download PDF</a>
        @if (in_array($invoice->status, \App\Models\Invoice::OPEN_STATUSES))
            {{-- Opens WhatsApp with the reminder typed; once the WhatsApp API is connected it sends it for you instead. --}}
            <x-whatsapp-reminder :client="$invoice->client" kind="invoice" label="WhatsApp reminder" send="sendWhatsApp" class="!px-3.5 !py-2 !text-sm"
                :values="['item' => $invoice->number, 'date' => fmt_date($invoice->due_date, 'receipt'), 'amount' => money($invoice->balance, $invoice->currency)]" />
        @endif
        @if (in_array($invoice->status, \App\Models\Invoice::OPEN_STATUSES))
            <a href="{{ route('payments.create', ['client' => $invoice->client_id, 'invoice' => $invoice->id]) }}" class="btn-primary">Record Payment</a>
        @endif
    </x-page-header>

    @if ($invoice->status === 'draft')
        <div class="card mb-5 flex flex-wrap items-end gap-4 border-brand-200 bg-brand-50 p-4">
            <div class="min-w-64 flex-1 text-sm text-brand-800">
                <p class="font-semibold">This invoice is a draft and has not been sent.</p>
                <p>Planned send date: <strong>{{ fmt_date($invoice->planned_send_date, 'not set') }}</strong>. Send it to the client yourself (download the PDF), then mark it as sent here.</p>
            </div>
            <x-field label="Sent on" name="sentDate"><input type="date" class="input" wire:model="sentDate" max="{{ today_local()->toDateString() }}"></x-field>
            <button type="button" class="btn-primary mb-0.5" wire:click="markSent" wire:confirm="Mark {{ $invoice->number }} as sent? It can no longer be edited.">Mark as Sent</button>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-3">
        <div class="space-y-5 xl:col-span-2">
            <section class="card">
                <dl class="grid gap-4 border-b border-stone-200 p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2"><dt class="label">Client</dt><dd><a class="link" href="{{ route('clients.show', ['client' => $invoice->client_id, 'tab' => 'finance']) }}">{{ $invoice->client->name }}</a></dd></div>
                    <div><dt class="label">Invoice date</dt><dd>{{ fmt_date($invoice->invoice_date) }}</dd></div>
                    <div><dt class="label">Due date</dt><dd @class(['font-semibold text-red-700' => $invoice->isOverdue()])>{{ fmt_date($invoice->due_date) }}</dd></div>
                    <div><dt class="label">Planned send date</dt><dd>{{ fmt_date($invoice->planned_send_date) }}</dd></div>
                    <div><dt class="label">Sent date</dt><dd>{{ $invoice->sent_at ? fmt_date($invoice->sent_at->timezone(crm_tz())) : '—' }}</dd></div>
                    <div><dt class="label">Currency</dt><dd>{{ $invoice->currency }}</dd></div>
                    <div><dt class="label">Created by</dt><dd>{{ $invoice->creator?->name ?? '—' }}</dd></div>
                    @if ($invoice->gstKind() !== 'none')
                        <div><dt class="label">Place of supply</dt><dd>{{ \App\Support\Gst::stateName($invoice->place_of_supply) }}</dd></div>
                        <div><dt class="label">Client GSTIN</dt><dd class="font-mono">{{ $invoice->client_gstin ?: 'Unregistered (B2C)' }}</dd></div>
                    @endif
                    @if ($invoice->recipient_name) <div class="sm:col-span-2"><dt class="label">Recipient</dt><dd>{{ $invoice->recipient_name }} <span class="text-stone-500">{{ $invoice->recipient_email }}</span></dd></div> @endif
                    @if ($invoice->quote) <div class="sm:col-span-2"><dt class="label">Raised from quote</dt><dd>@can('sales.manage')<a class="link font-mono" href="{{ route('quotes.show', $invoice->quote) }}">{{ $invoice->quote->number }}</a>@else<span class="font-mono">{{ $invoice->quote->number }}</span>@endcan · revision {{ $invoice->quote_revision }} · <a class="link" href="{{ route('quotes.revision', [$invoice->quote, $invoice->quote_revision]) }}">issued PDF</a></dd></div> @endif
                </dl>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th class="w-full">Description</th>@if ($invoice->gstKind() !== 'none')<th>HSN / SAC</th>@endif<th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount</th></tr></thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                                <tr><td>{{ $item->description }}</td>@if ($invoice->gstKind() !== 'none')<td>{{ $item->hsn_sac ?: '—' }}</td>@endif<td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td><td class="num">{{ money($item->unit_price, $invoice->currency) }}</td><td class="num">{{ money($item->amount, $invoice->currency) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end border-t border-stone-200 p-4">
                    <dl class="w-72 space-y-1.5 text-sm">
                        <div class="flex justify-between"><dt class="text-stone-600">Subtotal</dt><dd class="tabular-nums">{{ money($invoice->subtotal, $invoice->currency) }}</dd></div>
                        @if ((float) $invoice->discount > 0) <div class="flex justify-between"><dt class="text-stone-600">Discount</dt><dd class="tabular-nums">− {{ money($invoice->discount, $invoice->currency) }}</dd></div> @endif
                        @foreach ($invoice->taxLines() as $label => $amount)
                            <div class="flex justify-between"><dt class="text-stone-600">{{ $label }}</dt><dd class="tabular-nums">{{ money($amount, $invoice->currency) }}</dd></div>
                        @endforeach
                        <div class="flex justify-between border-t border-stone-200 pt-1.5 font-semibold text-stone-900"><dt>Total</dt><dd class="tabular-nums">{{ money($invoice->total, $invoice->currency) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-stone-600">Paid</dt><dd class="tabular-nums">{{ money($invoice->amount_paid, $invoice->currency) }}</dd></div>
                        <div class="flex justify-between text-base font-semibold {{ $invoice->balance > 0 && $invoice->status !== 'draft' ? 'text-red-700' : 'text-stone-900' }}"><dt>Balance</dt><dd class="tabular-nums">{{ money($invoice->balance, $invoice->currency) }}</dd></div>
                    </dl>
                </div>
                @if ($invoice->notes) <div class="rich border-t border-stone-200 p-4 text-sm text-stone-600">{{ rich($invoice->notes) }}</div> @endif
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Payments applied</h2></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Date</th><th>Method</th><th>Payment To</th><th>Reference</th><th class="num">Applied</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($invoice->allocations as $allocation)
                                <tr>
                                    <td class="whitespace-nowrap">{{ fmt_date($allocation->payment->payment_date) }}</td>
                                    <td>{{ $allocation->payment->method?->name ?? '—' }}</td>
                                    <td>{{ $allocation->payment->receiver?->name ?? '—' }}</td>
                                    <td>{{ $allocation->payment->reference ?: '—' }}</td>
                                    <td class="num">{{ money($allocation->amount, $invoice->currency) }}</td>
                                    <td class="text-right"><a href="{{ route('payments.edit', $allocation->payment_id) }}" class="btn-ghost btn-sm">Open</a></td>
                                </tr>
                            @empty
                                <x-empty colspan="6">No payments applied yet.</x-empty>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="space-y-5">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Copy sent outside the CRM</h2></div>
                <div class="space-y-3 p-4 text-sm">
                    @if ($invoice->uploaded_pdf_path)
                        <p class="flex items-center justify-between gap-2">
                            <a class="link truncate" href="{{ route('invoices.uploaded', $invoice) }}"><x-icon name="download" class="inline size-4" /> {{ $invoice->uploaded_pdf_name }}</a>
                            <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="removeCopy" wire:confirm="Remove the uploaded copy?">Remove</button>
                        </p>
                    @else
                        <p class="text-stone-500">If the invoice was issued from another tool, upload that PDF so it stays with the record.</p>
                    @endif
                    <form wire:submit="saveCopy" class="space-y-2">
                        <input type="file" accept="application/pdf" class="input" wire:model="copy" aria-label="Invoice PDF copy">
                        @error('copy') <p class="field-error">{{ $message }}</p> @enderror
                        <button type="submit" class="btn-secondary btn-sm" wire:loading.attr="disabled" wire:target="copy,saveCopy">{{ $invoice->uploaded_pdf_path ? 'Replace copy' : 'Upload copy' }}</button>
                    </form>
                </div>
            </section>

            @if ($invoice->status !== 'void')
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Other actions</h2></div>
                    <div class="flex flex-wrap gap-2 p-4">
                        <button type="button" class="btn-danger" wire:click="void" wire:confirm="Void {{ $invoice->number }}? It will no longer count towards the client’s pending amount.">Void invoice</button>
                        @if ($invoice->status === 'draft')
                            <button type="button" class="btn-danger" wire:click="deleteDraft" wire:confirm="Delete this draft permanently?">Delete draft</button>
                        @endif
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
