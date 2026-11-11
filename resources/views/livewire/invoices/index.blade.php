<div>
    <x-page-header title="Invoices" :subtitle="$summary['count'].' '.str('invoice')->plural($summary['count']).' · '.money($summary['outstanding']).' outstanding'.($summary['overdue'] ? ' · '.$summary['overdue'].' overdue' : '')">
        <a href="{{ route('invoices.export', array_filter($this->filters())) }}" class="btn-secondary" title="Every invoice matching the filters below, as one PDF"><x-icon name="download" /> Download PDF</a>
        <a href="{{ route('payments.create') }}" class="btn-secondary">Record Payment</a>
        <a href="{{ route('invoices.create') }}" class="btn-primary"><x-icon name="plus" /> New Invoice</a>
    </x-page-header>

    <div class="card">
        <div class="grid gap-3 border-b border-stone-200 p-4 md:grid-cols-3 xl:grid-cols-6">
            <div class="relative md:col-span-3 xl:col-span-2">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
                <input type="search" class="input pl-9" placeholder="Search invoice number or client…" wire:model.live.debounce.300ms="search" aria-label="Search invoices">
            </div>
            <select class="input" wire:model.live="client" aria-label="Client">
                <option value="">All clients</option>
                @foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
            </select>
            <select class="input" wire:model.live="status" aria-label="Status">
                <option value="">All statuses</option>
                @foreach (\App\Models\Invoice::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
            <label class="flex items-center gap-2 text-xs font-semibold text-stone-500 uppercase">From <input type="date" class="input normal-case" wire:model.live="from"></label>
            <label class="flex items-center gap-2 text-xs font-semibold text-stone-500 uppercase">To <input type="date" class="input normal-case" wire:model.live="to"></label>
            @if ($search || $client || $status || $from || $to)
                <button type="button" class="link justify-self-start text-sm" wire:click="clearFilters">Clear filters</button>
            @endif
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Number</th><th>Client</th><th>Invoice date</th><th>Planned send</th><th>Sent</th><th>Due</th><th>Status</th><th class="num">Total</th><th class="num">Balance</th><th class="text-right">PDF</th></tr></thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr wire:key="invoice-{{ $invoice->id }}">
                            <td><a class="link" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                            <td><a class="hover:underline" href="{{ route('clients.show', ['client' => $invoice->client_id, 'tab' => 'finance']) }}">{{ $invoice->client->name }}</a></td>
                            <td class="whitespace-nowrap">{{ fmt_date($invoice->invoice_date) }}</td>
                            <td class="whitespace-nowrap">
                                @if ($invoice->status === 'draft' && $invoice->planned_send_date && days_until($invoice->planned_send_date) <= 0)
                                    <span class="badge-red-strong">Send now · {{ fmt_date($invoice->planned_send_date) }}</span>
                                @else {{ fmt_date($invoice->planned_send_date) }} @endif
                            </td>
                            <td class="whitespace-nowrap">{{ $invoice->sent_at ? fmt_date($invoice->sent_at->timezone(crm_tz())) : '—' }}</td>
                            <td @class(['whitespace-nowrap', 'font-semibold text-red-700' => $invoice->isOverdue()])>{{ fmt_date($invoice->due_date) }}</td>
                            <td><x-status :value="$invoice->display_status" :label="$invoice->display_status_label" /></td>
                            <td class="num">{{ money($invoice->total, $invoice->currency) }}</td>
                            <td class="num font-semibold">{{ in_array($invoice->status, ['draft', 'void']) ? '—' : money($invoice->balance, $invoice->currency) }}</td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('invoices.pdf', $invoice) }}" class="btn-ghost btn-sm" title="Download generated PDF"><x-icon name="download" /> PDF</a>
                                @if ($invoice->uploaded_pdf_path) <a href="{{ route('invoices.uploaded', $invoice) }}" class="btn-ghost btn-sm" title="Download the copy sent outside the CRM">Sent copy</a> @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="10">No invoices match these filters.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($invoices->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $invoices->links() }}</div> @endif
    </div>
</div>
