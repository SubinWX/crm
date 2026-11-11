<div class="space-y-5">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Invoiced (issued)" :value="money($financials['invoiced'])" hint="Sent, partially paid and paid invoices" />
        <x-stat label="Payments applied" :value="money($financials['applied'])" />
        <x-stat label="Pending amount" :value="money($financials['pending'])" hint="Issued invoices − applied payments" :tone="$financials['pending'] > 0 ? 'danger' : 'default'" />
        <x-stat label="Unallocated payments" :value="money($financials['unallocated'])" hint="Shown separately until applied" />
    </div>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Invoices</h2>
            <a href="{{ route('invoices.create', ['client' => $client->id]) }}" class="btn-secondary btn-sm"><x-icon name="plus" /> New Invoice</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Number</th><th>Date</th><th>Due</th><th>Status</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr wire:key="inv-{{ $invoice->id }}">
                            <td><a class="link" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                            <td class="whitespace-nowrap">{{ fmt_date($invoice->invoice_date) }}</td>
                            <td class="whitespace-nowrap">{{ fmt_date($invoice->due_date) }}</td>
                            <td><x-status :value="$invoice->display_status" :label="$invoice->display_status_label" /></td>
                            <td class="num">{{ money($invoice->total, $invoice->currency) }}</td>
                            <td class="num">{{ money($invoice->amount_paid, $invoice->currency) }}</td>
                            <td class="num font-semibold">{{ $invoice->status === 'void' || $invoice->status === 'draft' ? '—' : money($invoice->balance, $invoice->currency) }}</td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('invoices.pdf', $invoice) }}" class="btn-ghost btn-sm"><x-icon name="download" /> PDF</a>
                                @if ($invoice->uploaded_pdf_path)
                                    <a href="{{ route('invoices.uploaded', $invoice) }}" class="btn-ghost btn-sm">Sent copy</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="8">No invoices for this client yet.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Payment history</h2>
            <a href="{{ route('payments.create', ['client' => $client->id]) }}" class="btn-secondary btn-sm"><x-icon name="plus" /> Record Payment</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Date</th><th>Payment type</th><th>Payment To</th><th>Reference</th><th>Applied to</th><th class="num">Amount</th><th class="num">Unallocated</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr wire:key="pay-{{ $payment->id }}">
                            <td class="whitespace-nowrap">{{ fmt_date($payment->payment_date) }}</td>
                            <td>{{ $payment->method?->name ?? '—' }}</td>
                            <td>{{ $payment->receiver?->name ?? '—' }}@if ($payment->receiver && ! $payment->receiver->is_active) <span class="text-xs text-stone-500">(inactive)</span>@endif</td>
                            <td>{{ $payment->reference ?: '—' }}</td>
                            <td>
                                @forelse ($payment->allocations as $allocation)
                                    <div class="text-xs whitespace-nowrap"><a class="link" href="{{ route('invoices.show', $allocation->invoice_id) }}">{{ $allocation->invoice?->number }}</a> · {{ money($allocation->amount) }}</div>
                                @empty <span class="text-stone-400">—</span> @endforelse
                            </td>
                            <td class="num">{{ money($payment->amount) }}</td>
                            <td class="num">@if ($payment->unallocated > 0) <span class="badge-amber">{{ money($payment->unallocated) }}</span> @else — @endif</td>
                            <td class="text-right whitespace-nowrap"><a href="{{ route('payments.edit', $payment) }}" class="btn-ghost btn-sm">{{ $payment->unallocated > 0 ? 'Apply' : 'Open' }}</a></td>
                        </tr>
                    @empty
                        <x-empty colspan="8">No payments recorded for this client.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
