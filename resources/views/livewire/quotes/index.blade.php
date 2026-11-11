<div>
    @unless ($embedded)
        <x-page-header title="Quotes & Proposals" subtitle="Drafts, issued quotes and their PDFs.">
            @can('admin') <a href="{{ route('quote-templates.index') }}" class="btn-secondary">Templates</a> @endcan
            <a href="{{ $newQuoteUrl }}" class="btn-primary"><x-icon name="plus" /> New Quote</a>
        </x-page-header>
    @endunless

    <div class="card">
        <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
            @if ($embedded) <h2 class="card-title">Quotes &amp; proposals</h2> @endif
            <div class="relative w-full md:w-80">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
                <input type="search" class="input pl-9" placeholder="Search number, title, customer…" wire:model.live.debounce.300ms="search" aria-label="Search quotes">
            </div>
            <select class="input w-auto" wire:model.live="status" aria-label="Status">
                <option value="">All statuses</option>
                @foreach (\App\Models\Quote::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
            @if ($embedded) <a href="{{ $newQuoteUrl }}" class="btn-primary btn-sm ml-auto"><x-icon name="plus" /> New Quote</a> @endif
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Quote</th><th>Title</th>@unless ($embedded)<th>For</th>@endunless<th>Issued</th><th>Expires</th><th>Follow-up</th><th>Status</th><th class="num">One-time</th><th class="num">Recurring</th><th class="text-right">PDF</th></tr></thead>
                <tbody>
                    @forelse ($quotes as $quote)
                        @php $totals = $quote->totals(); @endphp
                        <tr wire:key="quote-{{ $quote->id }}">
                            <td class="whitespace-nowrap"><a class="link font-mono" href="{{ route('quotes.show', $quote) }}">{{ $quote->number }}</a>@if ($quote->revision > 1) <span class="text-xs text-stone-500">rev {{ $quote->revision }}</span>@endif</td>
                            <td class="max-w-72"><a class="font-medium text-stone-900 hover:underline" href="{{ route('quotes.show', $quote) }}">{{ $quote->title }}</a></td>
                            @unless ($embedded)
                                <td>{{ $quote->partyName() }} @if (! $quote->client_id && $quote->lead_id) <span class="badge-blue">Lead</span> @endif<div class="text-xs text-stone-500">{{ $quote->recipient_name }}</div></td>
                            @endunless
                            <td class="whitespace-nowrap">{{ fmt_date($quote->issue_date) }}</td>
                            <td @class(['whitespace-nowrap', 'font-semibold text-red-700' => $quote->isExpired()])>{{ fmt_date($quote->expiry_date) }}</td>
                            <td class="whitespace-nowrap">
                                @if ($quote->follow_up_date && in_array($quote->status, ['draft', 'sent']) && days_until($quote->follow_up_date) <= 0) <span class="badge-red-strong">{{ fmt_date($quote->follow_up_date) }}</span>
                                @else {{ fmt_date($quote->follow_up_date) }} @endif
                            </td>
                            <td><x-status :value="['draft' => 'void', 'sent' => 'sent', 'accepted' => 'paid', 'declined' => 'void', 'expired' => 'overdue'][$quote->display_status]" :label="\App\Models\Quote::STATUSES[$quote->display_status]" /></td>
                            <td class="num">{{ isset($totals['one_time']) ? money($totals['one_time']['total'], $quote->currency) : '—' }}</td>
                            <td class="num text-xs">
                                @forelse (collect($totals)->except('one_time') as $frequency => $t) <div>{{ money($t['total'], $quote->currency) }}{{ \App\Models\Quote::FREQUENCY_SUFFIX[$frequency] }}</div> @empty — @endforelse
                            </td>
                            <td class="text-right whitespace-nowrap"><a href="{{ route('quotes.pdf', $quote) }}" class="btn-ghost btn-sm"><x-icon name="download" /> PDF</a></td>
                        </tr>
                    @empty
                        <x-empty :colspan="$embedded ? 9 : 10">No quotes {{ $embedded ? 'here yet' : 'match these filters' }}.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($quotes->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $quotes->links() }}</div> @endif
    </div>
</div>
