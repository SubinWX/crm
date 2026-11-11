<div>
    <div class="mb-1 text-sm"><a href="{{ route('quotes.index') }}" class="link">← Quotes &amp; Proposals</a></div>
    <x-page-header :title="$quote->title" :subtitle="$quote->number.' · revision '.$quote->revision.' · for '.$quote->partyName()">
        <x-status :value="['draft' => 'void', 'sent' => 'sent', 'accepted' => 'paid', 'declined' => 'void', 'expired' => 'overdue'][$quote->display_status]" :label="\App\Models\Quote::STATUSES[$quote->display_status]" />
        <a href="{{ route('quotes.pdf', ['quote' => $quote, 'inline' => 1]) }}" target="_blank" rel="noopener" class="btn-secondary">Preview PDF</a>
        <a href="{{ route('quotes.pdf', $quote) }}" class="btn-secondary"><x-icon name="download" /> Download PDF</a>
        @if ($quote->status === 'draft')
            <a href="{{ route('quotes.edit', $quote) }}" class="btn-secondary">Edit</a>
            <button type="button" class="btn-primary" wire:click="markSent" wire:confirm="Mark {{ $quote->number }} as sent? The PDF for this revision is stored and can no longer be edited.">Mark as Sent</button>
        @elseif ($quote->status === 'sent')
            <button type="button" class="btn-secondary" wire:click="respond('declined')" wire:confirm="Mark this quote as declined?">Declined</button>
            <button type="button" class="btn-primary" wire:click="respond('accepted')" wire:confirm="Mark this quote as accepted?">Accepted</button>
        @endif
    </x-page-header>

    @if ($quote->status === 'draft')
        <div class="card mb-5 border-brand-200 bg-brand-50 p-4 text-sm text-brand-800">This is a draft{{ $quote->revision > 1 ? ' of revision '.$quote->revision.'. Earlier revisions below keep their issued PDFs' : '' }}. Preview the PDF, send it to the customer yourself, then use <strong>Mark as Sent</strong> to store the issued copy.</div>
    @elseif ($quote->isExpired())
        <div class="card mb-5 border-red-200 bg-red-50 p-4 text-sm text-red-800">This quote expired on {{ fmt_date($quote->expiry_date) }} without a response. Revise it to issue a new version with fresh dates.</div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-5">
            <section class="card">
                <dl class="grid gap-4 p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="label">Recipient</dt><dd>{{ $quote->recipient_name }}<div class="text-xs text-stone-500">{{ $quote->recipient_email }}</div></dd></div>
                    <div><dt class="label">Company</dt><dd>
                        @if ($quote->client && auth()->user()->can('clients.view')) <a class="link" href="{{ route('clients.show', $quote->client) }}">{{ $quote->client->name }}</a>
                        @elseif ($quote->lead) <a class="link" href="{{ route('leads.show', $quote->lead) }}">{{ $quote->lead->name }}</a> <span class="badge-blue">Lead</span>
                        @else {{ $quote->recipient_company ?: '—' }} @endif
                    </dd></div>
                    <div><dt class="label">Issued</dt><dd>{{ fmt_date($quote->issue_date) }}</dd></div>
                    <div><dt class="label">Expires</dt><dd @class(['font-semibold text-red-700' => $quote->isExpired()])>{{ fmt_date($quote->expiry_date) }}</dd></div>
                    <div><dt class="label">Sent</dt><dd>{{ fmt_datetime($quote->sent_at) }}</dd></div>
                    <div><dt class="label">Response</dt><dd>{{ $quote->responded_at ? \App\Models\Quote::STATUSES[$quote->status].' · '.fmt_date($quote->responded_at->timezone(crm_tz())) : '—' }}</dd></div>
                    <div><dt class="label">Currency</dt><dd>{{ $quote->currency }}</dd></div>
                    <div><dt class="label">Prepared by</dt><dd>{{ $quote->creator?->name ?? '—' }}</dd></div>
                    @if ($quote->template_names) <div class="sm:col-span-2 lg:col-span-4"><dt class="label">Built from</dt><dd class="flex flex-wrap gap-1">@foreach (explode(', ', $quote->template_names) as $name) <span class="badge-gray">{{ $name }}</span> @endforeach</dd></div> @endif
                </dl>
            </section>

            @if ($quote->sections->where('is_hidden', false)->isNotEmpty())
                <section class="card divide-y divide-stone-200">
                    @foreach ($quote->sections->where('is_hidden', false) as $section)
                        <div class="p-4 sm:p-5">
                            <h2 class="text-sm font-semibold text-stone-900">{{ $section->title }}</h2>
                            <div class="mt-1.5 text-sm leading-relaxed break-words whitespace-pre-line text-stone-700">{{ $section->body }}</div>
                        </div>
                    @endforeach
                </section>
            @endif

            <section class="card">
                <div class="card-header"><h2 class="card-title">Pricing</h2></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th class="w-full">Service / product</th><th class="num">Qty</th><th class="num">Unit price</th><th>Billing</th><th class="num">Amount</th></tr></thead>
                        <tbody>
                            @forelse ($quote->items as $item)
                                <tr>
                                    <td><p class="font-medium text-stone-900">{{ $item->name }}</p>@if ($item->description) <p class="text-xs text-stone-500">{{ $item->description }}</p> @endif</td>
                                    <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                                    <td class="num">{{ money($item->unit_price, $quote->currency) }}</td>
                                    <td class="whitespace-nowrap">{{ \App\Models\Quote::FREQUENCIES[$item->billing_frequency] ?? $item->billing_frequency }}</td>
                                    <td class="num">{{ money($item->amount, $quote->currency) }}</td>
                                </tr>
                            @empty
                                <x-empty colspan="5">No line items.</x-empty>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-stone-200 p-4">
                    @include('livewire.quotes._totals', ['currency' => $quote->currency, 'discountPercent' => $quote->discount_percent, 'taxRate' => $quote->tax_rate])
                </div>
            </section>
        </div>

        <aside class="space-y-5">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Follow-up</h2></div>
                <form wire:submit="saveFollowUp" class="flex items-end gap-2 p-4">
                    <x-field label="Follow up on" name="followUpDate" class="flex-1"><input type="date" class="input" wire:model="followUpDate"></x-field>
                    <button type="submit" class="btn-secondary mb-0.5">Save</button>
                </form>
            </section>

            @if ($quote->status === 'accepted')
                <section class="card">
                    <div class="card-header"><h2 class="card-title">Invoicing</h2></div>
                    <div class="space-y-3 p-4 text-sm">
                        @foreach ($billable as $row)
                            <div class="flex items-baseline justify-between gap-3">
                                <span class="min-w-0 truncate">{{ $row['name'] }}</span>
                                <span class="text-right text-xs whitespace-nowrap text-stone-600">
                                    {{ money($row['invoiced'], $quote->currency) }} invoiced
                                    @if (! $row['recurring']) · <strong @class(['text-stone-900' => $row['remaining'] > 0, 'text-emerald-700' => $row['remaining'] <= 0])>{{ $row['remaining'] > 0 ? money($row['remaining'], $quote->currency).' left' : 'fully billed' }}</strong>
                                    @else · {{ \App\Models\Quote::FREQUENCIES[$row['frequency']] }} @endif
                                </span>
                            </div>
                        @endforeach
                        @if (! $quote->client_id)
                            <p class="rounded-md bg-amber-50 p-3 text-amber-800">This quote belongs to a lead. Convert the lead to a client before invoicing.</p>
                            <button type="button" class="btn-primary w-full" wire:click="convertLead" wire:confirm="Convert {{ $quote->lead?->name }} to a client? Its contacts, notes, follow-ups and quotes move across.">Convert lead to client</button>
                        @else
                            @can('finance') <button type="button" class="btn-primary w-full" wire:click="startInvoice">Create invoice from this quote</button>
                            @else <p class="text-xs text-stone-500">A Super Admin can raise invoices from this quote.</p> @endcan
                        @endif
                        @foreach ($invoices as $invoice)
                            <p class="flex items-center justify-between gap-2 border-t border-stone-100 pt-2">
                                <a class="link" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number }}</a>
                                <span class="text-xs text-stone-600">rev {{ $invoice->quote_revision }} · {{ money($invoice->total, $invoice->currency) }} · {{ $invoice->display_status_label }}</span>
                            </p>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="card">
                <div class="card-header"><h2 class="card-title">Issued versions</h2></div>
                @forelse ($quote->revisions as $revision)
                    <div class="flex items-center justify-between gap-3 border-b border-stone-100 px-4 py-2.5 text-sm last:border-b-0">
                        <span>Revision {{ $revision->revision }}<span class="block text-xs text-stone-500">Sent {{ fmt_datetime($revision->issued_at) }}{{ $revision->creator ? ' · '.$revision->creator->name : '' }}</span></span>
                        <a href="{{ route('quotes.revision', [$quote, $revision->revision]) }}" class="btn-ghost btn-sm"><x-icon name="download" /> PDF</a>
                    </div>
                @empty
                    <x-empty>Nothing issued yet.</x-empty>
                @endforelse
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">Other actions</h2></div>
                <div class="flex flex-wrap gap-2 p-4">
                    @if ($quote->status !== 'draft')
                        <button type="button" class="btn-secondary" wire:click="revise" wire:confirm="Start revision {{ $quote->revision + 1 }}? The issued PDF of revision {{ $quote->revision }} is kept.">Revise quote</button>
                    @elseif ($quote->revisions->isEmpty())
                        <button type="button" class="btn-danger" wire:click="deleteDraft" wire:confirm="Delete this draft permanently?">Delete draft</button>
                    @endif
                </div>
                @if ($quote->notes) <p class="border-t border-stone-200 p-4 text-sm whitespace-pre-line text-stone-600"><span class="label">Internal notes</span>{{ $quote->notes }}</p> @endif
            </section>
        </aside>
    </div>

    @if ($showInvoiceForm)
        <x-modal title="Create invoice from {{ $quote->number }}" size="max-w-3xl">
            <form wire:submit="createInvoice" class="space-y-4">
                <p class="text-sm text-stone-600">Tick the lines to bill now and adjust the amount for a deposit or milestone. Amounts are after the quote discount and before tax ({{ (float) $quote->tax_rate }}%).</p>
                <div class="table-wrap rounded-md border border-stone-200">
                    <table class="table">
                        <thead><tr><th></th><th class="w-full">Line</th><th class="num">Quoted</th><th class="num">Already invoiced</th><th class="num">Left</th><th class="num">Bill now</th></tr></thead>
                        <tbody>
                            @foreach ($billable as $row)
                                <tr wire:key="bill-{{ $row['id'] }}">
                                    <td><input type="checkbox" class="rounded border-stone-300" wire:model="bill.{{ $row['id'] }}.selected" aria-label="Bill {{ $row['name'] }}"></td>
                                    <td>{{ $row['name'] }}@if ($row['recurring']) <span class="badge-blue">{{ \App\Models\Quote::FREQUENCIES[$row['frequency']] }}</span>@endif</td>
                                    <td class="num">{{ money($row['net'], $quote->currency) }}</td>
                                    <td class="num">{{ money($row['invoiced'], $quote->currency) }}</td>
                                    <td class="num">{{ $row['recurring'] ? 'per period' : money(max($row['remaining'], 0), $quote->currency) }}</td>
                                    <td class="num">
                                        <input type="number" step="0.01" min="0" class="input ml-auto w-32 text-right" wire:model="bill.{{ $row['id'] }}.amount" aria-label="Amount to bill for {{ $row['name'] }}">
                                        @error("bill.{$row['id']}.amount") <p class="field-error">{{ $message }}</p> @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('bill') <p class="field-error">{{ $message }}</p> @enderror
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Invoice date" name="invoiceDate" required><input type="date" class="input" wire:model="invoiceDate"></x-field>
                    <x-field label="Due date" name="invoiceDueDate"><input type="date" class="input" wire:model="invoiceDueDate"></x-field>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Create draft invoice</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
