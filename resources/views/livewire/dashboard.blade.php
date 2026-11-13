<div>
    <x-page-header :title="'Good '.(now(crm_tz())->hour < 12 ? 'morning' : (now(crm_tz())->hour < 17 ? 'afternoon' : 'evening')).', '.str(auth()->user()->name)->before(' ')" :subtitle="$today->format('l, d F Y')">
        @can('clients.create') <a href="{{ route('clients.create') }}" class="btn-primary"><x-icon name="plus" /> New Client</a> @endcan
        @can('finance')
            <a href="{{ route('invoices.create') }}" class="btn-secondary">New Invoice</a>
            <a href="{{ route('payments.create') }}" class="btn-secondary">Record Payment</a>
            <a href="{{ route('expenses.index', ['add' => 1]) }}" class="btn-secondary">Add Expense</a>
        @endcan
        @can('posters.manage') <a href="{{ route('posters.index', ['upload' => 1]) }}" class="btn-secondary">Upload Poster</a> @endcan
    </x-page-header>

    <x-pending-items />

    {{-- Phone: the things people do from their phone, one tap away. --}}
    @can('workspace')
        <div class="mb-5 grid grid-cols-3 gap-2 sm:hidden">
            <a href="{{ route('attendance') }}" class="card flex flex-col items-center gap-1 px-2 py-3 text-center text-xs font-semibold text-stone-800"><x-icon name="check" class="size-6 text-brand-700" /> Check in / out</a>
            <a href="{{ route('leave', ['apply' => 1]) }}" class="card flex flex-col items-center gap-1 px-2 py-3 text-center text-xs font-semibold text-stone-800"><x-icon name="calendar" class="size-6 text-brand-700" /> Apply for leave</a>
            <a href="{{ route('timesheet') }}" class="card flex flex-col items-center gap-1 px-2 py-3 text-center text-xs font-semibold text-stone-800"><x-icon name="clock" class="size-6 text-brand-700" /> Timesheet</a>
        </div>
    @endcan

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if ($canClients)
            <x-stat label="Clients added this month" :value="$clientsThisMonth" :hint="$activeClients.' active '.str('client')->plural($activeClients).' in total'" :href="route('clients.index')" />
        @endif
        @if ($canHosting)
            <x-stat label="Overdue renewals" :value="$hostingOverdue->count()" :hint="$hostingWeek->count().' more due within 7 days'" :tone="$hostingOverdue->count() ? 'danger' : 'default'" :href="route('renewals.index', ['type' => 'hosting', 'hdue' => 'overdue'])" />
        @endif
        @if ($canFinance)
            <x-stat label="Outstanding invoice balance" :value="money($outstanding)" :hint="$openInvoices.' open · '.$overdueInvoices.' overdue'" :href="route('invoices.index')" />
            <x-stat label="Expenses this month" :value="money($monthExpenses)" :hint="$today->format('F Y')" :href="route('expenses.index')" />
        @endif
    </div>

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
        @if ($canHosting)
            {{-- Hosting renewals --}}
            <section class="card xl:col-span-2">
                <div class="card-header">
                    <h2 class="card-title">Hosting payments</h2>
                    <a href="{{ route('renewals.index') }}" class="link text-xs">Hosting &amp; Renewals</a>
                </div>
                @php
                    $groups = [
                        ['Overdue', $hostingOverdue, 'badge-red-solid', 'overdue'],
                        ['Due within 7 days', $hostingWeek, 'badge-red-strong', 'week'],
                        ['Due within 2 months', $hostingTwoMonths, 'badge-red', 'two_months'],
                    ];
                @endphp
                <div class="grid grid-cols-1 divide-y divide-stone-200 md:grid-cols-3 md:divide-x md:divide-y-0">
                    @foreach ($groups as [$label, $records, $badge, $filter])
                        <div class="min-w-0 p-4">
                            <a href="{{ route('renewals.index', ['type' => 'hosting', 'hdue' => $filter]) }}" class="mb-3 flex items-center justify-between gap-2">
                                <span class="text-xs font-semibold tracking-wide text-stone-600 uppercase">{{ $label }}</span>
                                <span class="{{ $records->count() ? $badge : 'badge-gray' }}">{{ $records->count() }}</span>
                            </a>
                            @forelse ($records->take(5) as $record)
                                <a href="{{ route('clients.show', ['client' => $record->client_id, 'tab' => 'renewals']) }}" class="-mx-2 block rounded-md px-2 py-1.5 hover:bg-stone-50">
                                    <span class="flex items-baseline justify-between gap-2">
                                        <span class="truncate text-sm font-medium text-stone-900">{{ $record->domain }}</span>
                                        <span class="text-sm tabular-nums whitespace-nowrap text-stone-900">{{ money($record->renewal_amount) }}</span>
                                    </span>
                                    <span class="block truncate text-xs text-stone-500">{{ $record->client->name }}</span>
                                        <span class="block text-xs">
                                        <span class="font-semibold whitespace-nowrap text-red-700">{{ fmt_date($record->next_payment_date) }} · {{ $record->urgencyLabel() }}</span>
                                    </span>
                                </a>
                            @empty
                                <p class="text-sm text-stone-500">Nothing here.</p>
                            @endforelse
                            @if ($records->count() > 5) <a href="{{ route('renewals.index', ['type' => 'hosting', 'hdue' => $filter]) }}" class="link mt-2 block text-xs">+ {{ $records->count() - 5 }} more</a> @endif
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-stone-200">
                    <p class="px-4 pt-3 text-xs font-semibold tracking-wide text-stone-600 uppercase">Next hosting payments</p>
                    <div class="table-wrap">
                        <table class="table">
                            <tbody>
                                @forelse ($hostingNext as $record)
                                    <tr>
                                        <td class="font-medium text-stone-900">{{ $record->domain }}</td>
                                        <td>{{ $record->client->name }}</td>
                                        <td class="num">{{ money($record->renewal_amount) }}</td>
                                        <td @class(['whitespace-nowrap', 'font-semibold text-red-700' => in_array($record->urgency(), ['overdue', 'week', 'two_months'])])>{{ fmt_date($record->next_payment_date) }}</td>
                                        <td class="text-right"><x-urgency :record="$record" /></td>
                                    </tr>
                                @empty
                                    <x-empty colspan="5">No paid hosting records with a payment date yet.</x-empty>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        @endif
        @if ($canClients)

            {{-- Clients --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">Clients added per month</h2></div>
                @php $max = max(1, $monthlyClients->max('count')); @endphp
                <div class="space-y-2 p-4">
                    @foreach ($monthlyClients as $month)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-16 shrink-0 text-xs text-stone-500">{{ $month['label'] }}</span>
                            <div class="h-2 flex-1 rounded-full bg-stone-100"><div class="h-2 rounded-full bg-brand-500" style="width: {{ $month['count'] ? max(4, round($month['count'] / $max * 100)) : 0 }}%"></div></div>
                            <span class="w-6 text-right font-medium tabular-nums text-stone-900">{{ $month['count'] }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-stone-200">
                    <p class="px-4 pt-3 pb-1 text-xs font-semibold tracking-wide text-stone-600 uppercase">Recently added</p>
                    @forelse ($recentClients as $client)
                        <a href="{{ route('clients.show', $client) }}" class="flex items-center justify-between gap-3 px-4 py-2 text-sm hover:bg-stone-50">
                            <span class="min-w-0"><span class="block truncate font-medium text-stone-900">{{ $client->name }}</span><span class="block truncate text-xs text-stone-500">{{ $client->creator ? 'Added by '.$client->creator->name : ($client->accountManager?->name ?? '') }}</span></span>
                            <span class="text-xs whitespace-nowrap text-stone-500">{{ fmt_date($client->created_at->timezone(crm_tz())) }}</span>
                        </a>
                    @empty
                        <x-empty>No clients yet.</x-empty>
                    @endforelse
                </div>
            </section>
        @endif

        @if ($canFinance)
            <section class="card xl:col-span-2">
                <div class="card-header"><h2 class="card-title">Invoices scheduled to be sent</h2><a href="{{ route('invoices.index', ['status' => 'draft']) }}" class="link text-xs">All drafts</a></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Invoice</th><th>Client</th><th>Planned send date</th><th class="num">Total</th></tr></thead>
                        <tbody>
                            @forelse ($scheduledInvoices as $invoice)
                                <tr>
                                    <td><a class="link" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                                    <td>{{ $invoice->client->name }}</td>
                                    <td class="whitespace-nowrap">
                                        @if (days_until($invoice->planned_send_date) <= 0) <span class="badge-red-strong">Send now · {{ fmt_date($invoice->planned_send_date) }}</span>
                                        @else {{ fmt_date($invoice->planned_send_date) }} <span class="text-xs text-stone-500">· in {{ days_until($invoice->planned_send_date) }} {{ str('day')->plural(days_until($invoice->planned_send_date)) }}</span> @endif
                                    </td>
                                    <td class="num">{{ money($invoice->total, $invoice->currency) }}</td>
                                </tr>
                            @empty
                                <x-empty colspan="4">No draft invoices are waiting to be sent.</x-empty>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($canSales)
            <section class="card xl:col-span-2">
                <div class="card-header"><h2 class="card-title">Quotes awaiting a response <span class="font-normal text-stone-500">({{ $quotesAwaitingCount }}) · {{ $openLeads }} open {{ str('lead')->plural($openLeads) }}</span></h2><a href="{{ route('quotes.index', ['status' => 'sent']) }}" class="link text-xs">All sent quotes</a></div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>Quote</th><th>For</th><th>Sent</th><th>Expires</th><th>Follow-up</th></tr></thead>
                        <tbody>
                            @forelse ($quotesAwaiting as $quote)
                                <tr>
                                    <td><a class="link font-mono" href="{{ route('quotes.show', $quote) }}">{{ $quote->number }}</a><div class="max-w-56 truncate text-xs text-stone-500">{{ $quote->title }}</div></td>
                                    <td>{{ $quote->partyName() }}</td>
                                    <td class="whitespace-nowrap">{{ fmt_date($quote->sent_at?->timezone(crm_tz())) }}</td>
                                    <td class="whitespace-nowrap">{{ fmt_date($quote->expiry_date) }}</td>
                                    <td class="whitespace-nowrap">@if ($quote->follow_up_date && days_until($quote->follow_up_date) <= 0) <span class="badge-red-strong">{{ fmt_date($quote->follow_up_date) }}</span> @else {{ fmt_date($quote->follow_up_date) }} @endif</td>
                                </tr>
                            @empty
                                <x-empty colspan="5">No quotes are waiting for an answer.</x-empty>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($canCareers)
            {{-- Careers: job applications by status --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">Careers: applications by status</h2><a href="{{ route('careers.manage') }}" class="link text-xs">Careers overview</a></div>
                <div class="flex flex-wrap items-center gap-5 p-4">
                    <div class="relative size-36 shrink-0">
                        <svg viewBox="0 0 42 42" class="size-36 -rotate-90" role="img"
                             aria-label="Job applications by status: {{ $careers['total'] ? $careers['slices']->where('count', '>', 0)->map(fn ($s) => $s['label'].' '.$s['count'])->implode(', ') : 'none yet' }}">
                            <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#e7e5e4" stroke-width="6" />
                            @foreach ($careers['slices']->where('count', '>', 0) as $slice)
                                <circle cx="21" cy="21" r="15.9155" fill="none" stroke="{{ $slice['color'] }}" stroke-width="6"
                                        stroke-dasharray="{{ max(0, $slice['percent'] - ($careers['slices']->where('count', '>', 0)->count() > 1 ? 0.6 : 0)) }} 100" stroke-dashoffset="-{{ $slice['offset'] }}"><title>{{ $slice['label'] }}: {{ $slice['count'] }} ({{ $slice['percent'] }}%)</title></circle>
                            @endforeach
                        </svg>
                        <div class="absolute inset-0 grid place-items-center text-center leading-tight">
                            <span><span class="block text-2xl font-semibold tabular-nums text-stone-900">{{ $careers['total'] }}</span><span class="block text-[11px] text-stone-500">{{ $careers['total'] === 1 ? 'application' : 'applications' }}</span></span>
                        </div>
                    </div>
                    <ul class="min-w-40 flex-1 space-y-1.5 text-sm">
                        @foreach ($careers['slices'] as $slice)
                            <li>
                                <a href="{{ route('careers.applications', ['status' => $slice['status']]) }}" class="flex items-center gap-2 rounded px-1 hover:bg-stone-50">
                                    <span class="size-2.5 shrink-0 rounded-sm" style="background: {{ $slice['color'] }}"></span>
                                    <span class="flex-1 text-stone-700">{{ $slice['label'] }}</span>
                                    <span class="font-medium tabular-nums text-stone-900">{{ $slice['count'] }}</span>
                                    <span class="w-10 text-right text-xs tabular-nums text-stone-500">{{ $careers['total'] ? round($slice['percent']).'%' : '—' }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div class="grid grid-cols-2 divide-x divide-stone-200 border-t border-stone-200 text-center text-sm">
                    <a href="{{ route('careers.vacancies') }}" class="px-3 py-2.5 hover:bg-stone-50"><span class="block text-lg font-semibold tabular-nums text-stone-900">{{ $careers['openVacancies'] }}</span><span class="text-xs text-stone-500">Open vacancies</span></a>
                    <a href="{{ route('careers.applications') }}" class="px-3 py-2.5 hover:bg-stone-50"><span class="block text-lg font-semibold tabular-nums text-stone-900">{{ $careers['thisMonth'] }}</span><span class="text-xs text-stone-500">Applications this month</span></a>
                </div>
            </section>
        @endif

        <section @class(['card', 'xl:col-span-2' => ! $canFinance])>
            <div class="card-header"><h2 class="card-title">Overdue tasks &amp; follow-ups</h2><a href="{{ route('tasks.index', ['status' => 'overdue']) }}" class="link text-xs">View all</a></div>
            @forelse ($tasksOverdue as $task)
                <div class="flex items-center justify-between gap-3 border-b border-stone-100 px-4 py-2.5 text-sm last:border-b-0">
                    <span class="min-w-0"><span class="block truncate font-medium text-stone-900">{{ $task->title }}</span><span class="block truncate text-xs text-stone-500">{{ $task->subjectName() }} · {{ $task->assignee?->name ?? 'Unassigned' }}</span></span>
                    <span class="badge-red-solid">Overdue · {{ fmt_date($task->due_date) }}</span>
                </div>
            @empty
                <x-empty>No overdue tasks.</x-empty>
            @endforelse
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Tasks &amp; follow-ups due this month</h2><a href="{{ route('tasks.index') }}" class="link text-xs">View all</a></div>
            @forelse ($tasksThisMonth as $task)
                <div class="flex items-center justify-between gap-3 border-b border-stone-100 px-4 py-2.5 text-sm last:border-b-0">
                    <span class="min-w-0"><span class="block truncate font-medium text-stone-900">{{ $task->title }}</span><span class="block truncate text-xs text-stone-500">{{ $task->subjectName() }} · {{ $task->assignee?->name ?? 'Unassigned' }}</span></span>
                    <span class="text-xs whitespace-nowrap text-stone-600">{{ fmt_date($task->due_date) }}</span>
                </div>
            @empty
                <x-empty>Nothing else due this month.</x-empty>
            @endforelse
        </section>

        @if ($canAdmin)
            <section class="card xl:col-span-2">
                <div class="card-header"><h2 class="card-title">Recent sign-in and account activity</h2><a href="{{ route('activity.index') }}" class="link text-xs">Activity Log</a></div>
                @forelse ($recentActivity as $entry)
                    <div class="flex items-center justify-between gap-3 border-b border-stone-100 px-4 py-2.5 text-sm last:border-b-0">
                        <span class="min-w-0 truncate"><span class="{{ $entry->action === 'login_failed' ? 'badge-red' : 'badge-gray' }} mr-2">{{ str($entry->action)->replace('_', ' ') }}</span>{{ $entry->description }}</span>
                        <span class="text-xs whitespace-nowrap text-stone-500">{{ fmt_datetime($entry->created_at) }}</span>
                    </div>
                @empty
                    <x-empty>No activity yet.</x-empty>
                @endforelse
            </section>
        @endif
    </div>
</div>
