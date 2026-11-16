<div>
    <x-page-header title="Reports" :subtitle="fmt_date($from).' to '.fmt_date($to)">
        <select class="input w-auto" wire:model.live="period" aria-label="Period">
            @foreach (\App\Livewire\Finance\Reports::PERIODS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
        </select>
    </x-page-header>

    <div class="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <x-stat label="Money received" :value="money($totals['received'])" hint="Payments recorded in the period" />
        <x-stat label="Money spent" :value="money($totals['spent'])" hint="Expenses recorded in the period" />
        <x-stat label="Profit" :value="money($totals['profit'])" hint="Received minus spent" :tone="$totals['profit'] < 0 ? 'danger' : 'default'" />
        <x-stat label="Invoiced" :value="money($totals['invoiced'])" hint="Before tax, sent invoices only" />
    </div>

    <section class="card mb-5">
        <div class="card-header"><h2 class="card-title">Money in and out by month</h2>
            <span class="flex items-center gap-3 text-xs text-stone-600"><span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-brand-600"></span> Received</span><span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-stone-400"></span> Spent</span></span></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Month</th><th class="w-2/5"><span class="sr-only">Chart</span></th><th class="num">Invoiced</th><th class="num">Received</th><th class="num">Spent</th><th class="num">Profit</th></tr></thead>
                <tbody>
                    @foreach ($months as $m)
                        <tr @class(['text-stone-400' => $m['future']])>
                            <td class="whitespace-nowrap font-medium text-stone-900">{{ $m['label'] }}</td>
                            <td>
                                <div class="space-y-1" aria-hidden="true">
                                    <div class="h-2 rounded-sm bg-brand-600" style="width: {{ max($m['received'] > 0 ? 1 : 0, round($m['received'] / $peak * 100, 1)) }}%"></div>
                                    <div class="h-2 rounded-sm bg-stone-400" style="width: {{ max($m['spent'] > 0 ? 1 : 0, round($m['spent'] / $peak * 100, 1)) }}%"></div>
                                </div>
                            </td>
                            <td class="num">{{ money($m['invoiced']) }}</td>
                            <td class="num">{{ money($m['received']) }}</td>
                            <td class="num">{{ money($m['spent']) }}</td>
                            <td @class(['num font-medium', 'text-red-700' => $m['profit'] < 0, 'text-stone-900' => $m['profit'] >= 0])>{{ money($m['profit']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="bg-stone-50 font-semibold text-stone-900"><td class="px-3 py-3">Total</td><td></td><td class="num px-3">{{ money($totals['invoiced']) }}</td><td class="num px-3">{{ money($totals['received']) }}</td><td class="num px-3">{{ money($totals['spent']) }}</td><td class="num px-3">{{ money($totals['profit']) }}</td></tr></tfoot>
            </table>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Income by client</h2><span class="text-xs text-stone-500">Top 10 by money received</span></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Client</th><th class="num">Payments</th><th class="num">Received</th><th class="num">Share</th></tr></thead>
                    <tbody>
                        @forelse ($clients as $c)
                            <tr><td><a href="{{ route('clients.show', $c->id) }}" class="link">{{ $c->name }}</a></td><td class="num">{{ $c->payments }}</td><td class="num">{{ money($c->received) }}</td>
                                <td class="num">{{ $totals['received'] > 0 ? round($c->received / $totals['received'] * 100) : 0 }}%</td></tr>
                        @empty
                            <x-empty :colspan="4">No payments were recorded in this period.</x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Still owed by clients</h2><span class="text-xs text-stone-500">As of today · {{ money($owed) }} in total</span></div>
            <div class="grid grid-cols-2 gap-px border-b border-stone-200 bg-stone-200 sm:grid-cols-5">
                @foreach ($buckets as $label => $amount)
                    <div class="bg-white p-3"><p class="text-[11px] font-semibold tracking-wide text-stone-500 uppercase">{{ $label }}</p><p @class(['mt-0.5 text-sm font-semibold tabular-nums', 'text-red-700' => $amount > 0 && ! $loop->first, 'text-stone-900' => $amount <= 0 || $loop->first])>{{ money($amount) }}</p></div>
                @endforeach
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Client</th><th class="num">Owes</th><th class="num">Oldest invoice late by</th></tr></thead>
                    <tbody>
                        @forelse ($owing as $o)
                            <tr><td><a href="{{ route('clients.show', $o['id']) }}" class="link">{{ $o['name'] }}</a></td><td class="num">{{ money($o['balance']) }}</td><td class="num">{{ $o['late'] > 0 ? $o['late'].' '.str('day')->plural($o['late']) : 'Not late' }}</td></tr>
                        @empty
                            <x-empty :colspan="3">Nobody owes anything. Every sent invoice is paid.</x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card xl:col-span-2">
            <div class="card-header"><h2 class="card-title">Leads</h2><span class="text-xs text-stone-500">Leads added in the period</span></div>
            <div class="grid grid-cols-2 gap-px bg-stone-200 sm:grid-cols-4">
                <div class="bg-white p-4"><p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Leads added</p><p class="mt-1 text-2xl font-semibold tabular-nums text-stone-900">{{ $leads['total'] }}</p></div>
                <div class="bg-white p-4"><p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Won</p><p class="mt-1 text-2xl font-semibold tabular-nums text-stone-900">{{ $leads['won'] }}</p></div>
                <div class="bg-white p-4"><p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Lost</p><p class="mt-1 text-2xl font-semibold tabular-nums text-stone-900">{{ $leads['lost'] }}</p></div>
                <div class="bg-white p-4"><p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Won out of decided</p><p class="mt-1 text-2xl font-semibold tabular-nums text-stone-900">{{ $leads['rate'] !== null ? $leads['rate'].'%' : '—' }}</p><p class="text-xs text-stone-500">{{ $leads['open'] }} still open</p></div>
            </div>
            <div class="flex flex-wrap gap-x-6 gap-y-1 border-t border-stone-200 px-4 py-3 text-sm text-stone-700">
                @foreach ($leads['stages'] as $stage) <span>{{ $stage['label'] }}: <strong class="text-stone-900">{{ $stage['n'] }}</strong></span> @endforeach
            </div>
        </section>
    </div>
    <p class="mt-3 text-xs text-stone-500">Profit here is money received minus money spent in each month. It is a working figure for running the business, not a tax or audit statement.</p>
</div>
