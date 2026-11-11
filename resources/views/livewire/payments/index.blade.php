<div>
    <x-page-header title="Payments" :subtitle="$payments->total().' '.str('payment')->plural($payments->total()).' · '.money($total).' received'">
        <a href="{{ route('payments.export', array_filter($this->filters())) }}" class="btn-secondary" title="Every payment matching the filters below, as one PDF"><x-icon name="download" /> Download PDF</a>
        <a href="{{ route('payments.create') }}" class="btn-primary"><x-icon name="plus" /> Record Payment</a>
    </x-page-header>

    <div class="card">
        <div class="grid gap-3 border-b border-stone-200 p-4 md:grid-cols-3 xl:grid-cols-6">
            <input type="search" class="input md:col-span-3 xl:col-span-2" placeholder="Search client or reference…" wire:model.live.debounce.300ms="search" aria-label="Search payments">
            <select class="input" wire:model.live="client" aria-label="Client">
                <option value="">All clients</option>
                @foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
            </select>
            <select class="input" wire:model.live="method" aria-label="Payment method">
                <option value="">All methods</option>
                @foreach ($methods as $m) <option value="{{ $m->id }}">{{ $m->name }}</option> @endforeach
            </select>
            <select class="input" wire:model.live="receiver" aria-label="Payment To">
                <option value="">Payment To: anyone</option>
                @foreach ($receivers as $r) <option value="{{ $r->id }}">{{ $r->name }}{{ $r->is_active ? '' : ' (inactive)' }}</option> @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-stone-600"><input type="checkbox" class="rounded border-stone-300" wire:model.live="unallocated"> Unallocated only</label>
            <label class="flex items-center gap-2 text-xs font-semibold text-stone-500 uppercase">From <input type="date" class="input normal-case" wire:model.live="from"></label>
            <label class="flex items-center gap-2 text-xs font-semibold text-stone-500 uppercase">To <input type="date" class="input normal-case" wire:model.live="to"></label>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Date</th><th>Client</th><th>Payment method</th><th>Payment To</th><th>Reference</th><th>Applied to</th><th class="num">Amount</th><th class="num">Unallocated</th><th></th></tr></thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr wire:key="payment-{{ $payment->id }}">
                            <td class="whitespace-nowrap">{{ fmt_date($payment->payment_date) }}</td>
                            <td><a class="link text-stone-900" href="{{ route('clients.show', ['client' => $payment->client_id, 'tab' => 'finance']) }}">{{ $payment->client->name }}</a></td>
                            <td>{{ $payment->method?->name ?? '—' }}</td>
                            <td>{{ $payment->receiver?->name ?? '—' }}@if ($payment->receiver && ! $payment->receiver->is_active) <span class="text-xs text-stone-500">(inactive)</span>@endif</td>
                            <td>{{ $payment->reference ?: '—' }}</td>
                            <td>
                                @forelse ($payment->allocations as $a)
                                    <div class="text-xs whitespace-nowrap"><a class="link" href="{{ route('invoices.show', $a->invoice_id) }}">{{ $a->invoice?->number }}</a> · {{ money($a->amount) }}</div>
                                @empty <span class="text-stone-400">—</span> @endforelse
                            </td>
                            <td class="num">{{ money($payment->amount) }}</td>
                            <td class="num">@if ($payment->unallocated > 0) <span class="badge-amber">{{ money($payment->unallocated) }}</span> @else — @endif</td>
                            <td class="text-right"><a href="{{ route('payments.edit', $payment) }}" class="btn-ghost btn-sm">{{ $payment->unallocated > 0 ? 'Apply' : 'Open' }}</a></td>
                        </tr>
                    @empty
                        <x-empty colspan="9">No payments match these filters.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $payments->links() }}</div> @endif
    </div>
</div>
