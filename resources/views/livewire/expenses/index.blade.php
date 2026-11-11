<div>
    <x-page-header title="Expenses" subtitle="Daily spend, by category, with recurring bills kept separate.">
        <a href="{{ route('expenses.export', array_filter($this->filters())) }}" class="btn-secondary"><x-icon name="download" /> Export CSV</a>
        <a href="{{ route('expenses.export-pdf', array_filter($this->filters())) }}" class="btn-secondary" title="Every expense matching the filters below, as one PDF"><x-icon name="download" /> Download PDF</a>
        <button type="button" class="btn-primary" wire:click="create"><x-icon name="plus" /> Add Expense</button>
    </x-page-header>

    <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat label="Today" :value="money($todayTotal)" :hint="today_local()->format('d M Y')" />
        <x-stat label="This month" :value="money($monthTotal)" :hint="today_local()->format('F Y')" />
        <x-stat label="Selected period" :value="money($filteredTotal)" :hint="($from ? fmt_date($from) : 'Beginning').' → '.($to ? fmt_date($to) : 'today')" />
        <x-stat label="Recurring this month" :value="money($recurringMonthly)" hint="Monthly subscriptions and bills" />
    </div>

    <div class="mb-5 flex gap-6 border-b border-stone-200" role="tablist">
        <button type="button" role="tab" @class(['tab', 'tab-active' => $view === 'all']) wire:click="$set('view', 'all')">All expenses</button>
        <button type="button" role="tab" @class(['tab', 'tab-active' => $view === 'recurring']) wire:click="$set('view', 'recurring')">Recurring</button>
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="card">
            <div class="grid gap-3 border-b border-stone-200 p-4 md:grid-cols-3">
                <input type="search" class="input" placeholder="Search payee or description…" wire:model.live.debounce.300ms="search" aria-label="Search expenses">
                <select class="input" wire:model.live="category" aria-label="Category">
                    <option value="">All categories</option>
                    @foreach ($allCategories as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
                </select>
                <select class="input" wire:model.live="client" aria-label="Client or overhead">
                    <option value="">Clients &amp; overhead</option>
                    <option value="overhead">General overhead only</option>
                    @foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
                </select>
                <label class="flex items-center gap-2 text-xs font-semibold text-stone-500 uppercase">From <input type="date" class="input normal-case" wire:model.live="from"></label>
                <label class="flex items-center gap-2 text-xs font-semibold text-stone-500 uppercase">To <input type="date" class="input normal-case" wire:model.live="to"></label>
                <div class="flex flex-wrap items-center gap-1.5">
                    @foreach (['today' => 'Today', 'month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year', 'all' => 'All time'] as $key => $label)
                        <button type="button" class="btn-ghost btn-sm border border-stone-200" wire:click="setRange('{{ $key }}')">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Date</th><th>Payee / vendor</th><th>Category</th><th>Linked to</th><th>Method</th>@if ($view === 'recurring')<th>Repeats</th>@endif<th class="num">Amount</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                        @forelse ($expenses as $expense)
                            <tr wire:key="expense-{{ $expense->id }}">
                                <td class="whitespace-nowrap">{{ fmt_date($expense->expense_date) }}</td>
                                <td class="max-w-72">
                                    <p class="font-medium text-stone-900">{{ $expense->payee ?: '—' }} @if ($expense->is_recurring && $view !== 'recurring') <span class="badge-blue">Recurring</span> @endif</p>
                                    @if ($expense->description) <p class="line-clamp-1 text-xs text-stone-500">{{ $expense->description }}</p> @endif
                                </td>
                                <td>{{ $expense->category?->name ?? '—' }}</td>
                                <td>{{ $expense->client?->name ?? 'General overhead' }}</td>
                                <td>{{ $expense->method?->name ?? '—' }}</td>
                                @if ($view === 'recurring') <td>{{ \App\Models\Expense::FREQUENCIES[$expense->recurring_frequency] ?? '—' }}</td> @endif
                                <td class="num">{{ money($expense->amount) }}</td>
                                <td class="text-right whitespace-nowrap">
                                    @if ($expense->receipt_path) <a href="{{ route('expenses.receipt', $expense) }}" class="btn-ghost btn-sm" title="Download receipt"><x-icon name="download" /> Receipt</a> @endif
                                    <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $expense->id }})">Edit</button>
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $expense->id }})" wire:confirm="Delete this expense?">Delete</button>
                                </td>
                            </tr>
                        @empty
                            <x-empty :colspan="$view === 'recurring' ? 8 : 7">No {{ $view === 'recurring' ? 'recurring ' : '' }}expenses in this period.</x-empty>
                        @endforelse
                    </tbody>
                    @if ($expenses->total())
                        <tfoot><tr><td colspan="{{ $view === 'recurring' ? 6 : 5 }}" class="px-4 py-3 text-right text-sm font-semibold text-stone-600">Total for selection</td><td class="num px-4 py-3 text-sm font-semibold text-stone-900">{{ money($filteredTotal) }}</td><td></td></tr></tfoot>
                    @endif
                </table>
            </div>
            @if ($expenses->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $expenses->links() }}</div> @endif
        </div>

        <div class="space-y-5">
            <section class="card">
                <div class="card-header"><h2 class="card-title">Category breakdown</h2></div>
                <div class="space-y-3 p-4">
                    @forelse ($breakdown as $row)
                        @php $pct = $filteredTotal > 0 ? round($row->total / $filteredTotal * 100) : 0; @endphp
                        <div>
                            <div class="flex items-baseline justify-between gap-2 text-sm"><span class="truncate text-stone-800">{{ $row->name }}</span><span class="tabular-nums font-medium text-stone-900">{{ money($row->total) }}</span></div>
                            <div class="mt-1 flex items-center gap-2">
                                <div class="h-1.5 flex-1 rounded-full bg-stone-100"><div class="h-1.5 rounded-full bg-brand-500" style="width: {{ max($pct, 2) }}%"></div></div>
                                <span class="w-9 text-right text-xs tabular-nums text-stone-500">{{ $pct }}%</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-stone-500">Nothing to break down.</p>
                    @endforelse
                </div>
            </section>
            <section class="card">
                <div class="card-header"><h2 class="card-title">Daily totals</h2></div>
                <div class="max-h-64 overflow-y-auto">
                    @forelse ($daily as $row)
                        <div class="flex justify-between border-b border-stone-100 px-4 py-2 text-sm last:border-b-0"><span>{{ fmt_date($row->expense_date) }} <span class="text-xs text-stone-500">· {{ $row->entries }}</span></span><span class="tabular-nums font-medium">{{ money($row->total) }}</span></div>
                    @empty
                        <x-empty>No spend in this period.</x-empty>
                    @endforelse
                </div>
            </section>
            <section class="card">
                <div class="card-header"><h2 class="card-title">Monthly totals</h2><span class="text-xs text-stone-500">Last 6 months</span></div>
                @forelse ($monthly as $row)
                    <div class="flex justify-between border-b border-stone-100 px-4 py-2 text-sm last:border-b-0"><span>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $row->month.'-01')->format('F Y') }}</span><span class="tabular-nums font-medium">{{ money($row->total) }}</span></div>
                @empty
                    <x-empty>No expenses yet.</x-empty>
                @endforelse
            </section>
        </div>
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit expense' : 'Add expense'">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <x-field label="Date" name="form.expense_date" required><input class="input" type="date" wire:model="form.expense_date"></x-field>
                <x-field label="Amount ({{ setting('currency', 'INR') }})" name="form.amount" required><input class="input" type="number" step="0.01" min="0" wire:model="form.amount"></x-field>
                <x-field label="Category" name="form.category_id" required>
                    <select class="input" wire:model="form.category_id">
                        <option value="">Select…</option>
                        @foreach ($categories as $c) <option value="{{ $c->id }}">{{ $c->name }}{{ $c->is_active ? '' : ' (inactive)' }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Payee / vendor" name="form.payee"><input class="input" type="text" wire:model="form.payee"></x-field>
                <x-field label="Payment method" name="form.payment_method_id">
                    <select class="input" wire:model="form.payment_method_id">
                        <option value="">Select…</option>
                        @foreach ($methods as $m) <option value="{{ $m->id }}">{{ $m->name }}{{ $m->is_active ? '' : ' (inactive)' }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Linked to" name="form.client_id">
                    <select class="input" wire:model="form.client_id">
                        <option value="">General overhead</option>
                        @foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Description" name="form.description" class="md:col-span-2"><textarea class="input" rows="2" wire:model="form.description"></textarea></x-field>
                <x-field label="Recurring" name="form.is_recurring">
                    <label class="flex items-center gap-2 py-2 text-sm"><input type="checkbox" class="rounded border-stone-300" wire:model.live="form.is_recurring"> Subscription or regular bill</label>
                </x-field>
                @if ($form['is_recurring'])
                    <x-field label="Repeats" name="form.recurring_frequency">
                        <select class="input" wire:model="form.recurring_frequency">
                            @foreach (\App\Models\Expense::FREQUENCIES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                        </select>
                    </x-field>
                @endif
                <x-field label="Receipt (optional)" name="receipt" hint="PDF or image, up to 8 MB." class="md:col-span-2"><input type="file" class="input" accept=".pdf,image/png,image/jpeg,image/webp" wire:model="receipt"></x-field>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="receipt,save">Save expense</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
