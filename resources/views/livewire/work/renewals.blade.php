<div>
    @unless ($clientId)
        <x-page-header title="Hosting & Renewals" subtitle="Everything that renews: hosting, domains, maintenance contracts and retainers.">
            @if ($canManage && $type !== 'hosting') <button type="button" class="btn-primary" wire:click="create"><x-icon name="plus" /> Add renewal</button> @endif
        </x-page-header>

        <div class="mb-5 flex gap-6 overflow-x-auto border-b border-stone-200" role="tablist">
            @foreach (['' => 'Overview', 'hosting' => 'Paid Server Clients (Hosting)'] + \App\Models\Renewal::TYPES as $key => $label)
                <button type="button" role="tab" aria-selected="{{ $type === $key ? 'true' : 'false' }}" @class(['tab', 'tab-active' => $type === $key]) wire:click="$set('type', '{{ $key }}')">{{ $label }}</button>
            @endforeach
        </div>
    @endunless

    @if (! $clientId && $type === 'hosting')
        <livewire:hosting.index :key="'hosting-all'" />
    @elseif (! $clientId && $type === '')
        <div class="card">
            <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
                <input type="search" class="input w-full md:w-72" placeholder="Search client, domain, provider…" wire:model.live.debounce.300ms="search" aria-label="Search">
                <select class="input w-auto" wire:model.live="due" aria-label="Due"><option value="">Any date</option><option value="overdue">Overdue</option><option value="soon">Due within 60 days</option></select>
                <span class="ml-auto text-xs text-stone-500">{{ $overview->count() }} active {{ str('item')->plural($overview->count()) }} · pick a tab above to add or edit</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Renews on</th><th>Client</th><th>Item</th><th>Type</th><th>Provider</th><th class="num">Amount</th><th>Due</th></tr></thead>
                    <tbody>
                        @forelse ($overview as $row)
                            @php $days = days_until($row['date']); @endphp
                            <tr>
                                <td @class(['whitespace-nowrap', 'font-semibold text-red-700' => $days !== null && $days <= 60])>{{ fmt_date($row['date']) }}</td>
                                <td><a class="link text-stone-900" href="{{ route('clients.show', ['client' => $row['client_id'], 'tab' => 'renewals']) }}">{{ $row['client'] }}</a></td>
                                <td class="font-medium text-stone-900">{{ $row['name'] }}</td>
                                <td><button type="button" class="{{ $row['type'] === 'hosting' ? 'badge-blue' : 'badge-gray' }} cursor-pointer" wire:click="$set('type', '{{ $row['type'] }}')">{{ $row['label'] }}</button></td>
                                <td>{{ $row['provider'] ?: '—' }}</td>
                                <td class="num">{{ money($row['amount']) }}<div class="text-xs text-stone-500">{{ \App\Models\HostingRecord::FREQUENCIES[$row['frequency']] ?? $row['frequency'] }}</div></td>
                                <td>
                                    @if ($days === null) <span class="text-stone-400">No date</span>
                                    @elseif ($days < 0) <span class="badge-red-solid">Overdue by {{ abs($days) }} {{ str('day')->plural(abs($days)) }}</span>
                                    @elseif ($days <= 7) <span class="badge-red-strong">{{ $days === 0 ? 'Due today' : 'Due in '.$days.' '.str('day')->plural($days) }}</span>
                                    @elseif ($days <= 60) <span class="badge-red">Due in {{ $days }} days</span>
                                    @else <span class="badge-gray">In {{ $days }} days</span> @endif
                                </td>
                            </tr>
                        @empty
                            <x-empty colspan="7">Nothing to renew yet. Add hosting under Paid Server Clients, or a domain, maintenance contract or retainer under its tab.</x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
    <div class="card">
        <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
            @if ($clientId) <h2 class="card-title">Domain, maintenance and retainer renewals</h2> @endif
            <input type="search" class="input w-full md:w-64" placeholder="Search…" wire:model.live.debounce.300ms="search" aria-label="Search renewals">
            @if ($clientId) <select class="input w-auto" wire:model.live="type" aria-label="Type"><option value="">All types</option>@foreach (\App\Models\Renewal::TYPES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select> @endif
            <select class="input w-auto" wire:model.live="due" aria-label="Due"><option value="">Any date</option><option value="overdue">Overdue</option><option value="soon">Due within 60 days</option></select>
            @if ($clientId && $canManage) <button type="button" class="btn-primary btn-sm ml-auto" wire:click="create"><x-icon name="plus" /> Add renewal</button> @endif
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Renewal</th>@unless ($clientId)<th>Client</th>@endunless<th>Type</th><th>Provider</th><th>Renews on</th><th class="num">Amount</th><th>Due</th><th>Reminders</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($renewals as $renewal)
                        @php $urgency = $renewal->urgency(); $days = $renewal->daysUntilDue(); @endphp
                        <tr wire:key="renewal-{{ $renewal->id }}">
                            <td><span class="font-medium text-stone-900">{{ $renewal->name }}</span> @if ($renewal->status !== 'active') <span class="badge-gray">Cancelled</span> @endif</td>
                            @unless ($clientId) <td><a class="link" href="{{ route('clients.show', ['client' => $renewal->client_id, 'tab' => 'renewals']) }}">{{ $renewal->client->name }}</a></td> @endunless
                            <td>{{ \App\Models\Renewal::TYPES[$renewal->type] ?? $renewal->type }}</td>
                            <td>{{ $renewal->provider ?: '—' }}</td>
                            <td @class(['whitespace-nowrap', 'font-semibold text-red-700' => in_array($urgency, ['overdue', 'soon'])])>{{ fmt_date($renewal->renewal_date) }}</td>
                            <td class="num">{{ money($renewal->amount) }}<div class="text-xs text-stone-500">{{ \App\Models\HostingRecord::FREQUENCIES[$renewal->frequency] ?? $renewal->frequency }}</div></td>
                            <td>
                                @if ($urgency === 'overdue') <span class="badge-red-solid">Overdue by {{ abs($days) }} {{ str('day')->plural(abs($days)) }}</span>
                                @elseif ($urgency === 'soon') <span class="badge-red-strong">{{ $days === 0 ? 'Due today' : 'Due in '.$days.' '.str('day')->plural($days) }}</span>
                                @elseif ($urgency === 'later') <span class="badge-gray">In {{ $days }} days</span>
                                @else <span class="text-stone-400">—</span> @endif
                            </td>
                            <td class="text-xs whitespace-nowrap text-stone-600">{{ $renewal->status === 'active' && $renewal->renewal_date ? collect(\App\Models\Renewal::reminderDays($renewal->type))->map(fn ($d) => $d.'d')->implode(', ').' before' : '—' }}</td>
                            <td class="text-right whitespace-nowrap">
                                @if ($renewal->status === 'active' && $renewal->renewal_date && $renewal->client)
                                    <x-whatsapp-reminder :client="$renewal->client" kind="renewal" send="sendWhatsApp({{ $renewal->id }})" :values="['item' => $renewal->name, 'date' => fmt_date($renewal->renewal_date), 'amount' => $renewal->amount !== null ? money($renewal->amount) : '—']" />
                                @endif
                                @if ($canManage && $renewal->status === 'active' && $renewal->renewal_date) <button type="button" class="btn-secondary btn-sm" wire:click="startRenewal({{ $renewal->id }})">Record Renewal</button> @endif
                                <button type="button" class="btn-ghost btn-sm" wire:click="toggle({{ $renewal->id }})">History</button>
                                @if ($canManage) <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $renewal->id }})">Edit</button> @endif
                            </td>
                        </tr>
                        @if ($expandedId === $renewal->id && $expanded)
                            <tr wire:key="renewal-detail-{{ $renewal->id }}"><td colspan="{{ $clientId ? 8 : 9 }}" class="!bg-stone-50">
                                <p class="mb-2 text-xs font-semibold tracking-wide text-stone-500 uppercase">Renewal history</p>
                                @forelse ($expanded->logs as $log)
                                    <p class="py-0.5 text-sm">Was due {{ fmt_date($log->due_date) }} · paid {{ fmt_date($log->paid_on) }} · {{ money($log->amount) }} · next {{ fmt_date($log->next_date) }} <span class="text-stone-500">· {{ $log->recorder?->name ?? '—' }}</span></p>
                                @empty <p class="text-sm text-stone-500">No renewals recorded yet.</p> @endforelse
                                @if ($expanded->notes) <p class="mt-2 text-sm whitespace-pre-line text-stone-600">{{ $expanded->notes }}</p> @endif
                                @if ($canManage) <button type="button" class="link mt-2 text-xs text-red-700" wire:click="delete({{ $renewal->id }})" wire:confirm="Delete {{ $renewal->name }} and its history?">Delete this renewal</button> @endif
                            </td></tr>
                        @endif
                    @empty
                        <x-empty :colspan="$clientId ? 8 : 9">No renewals here.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($renewals->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $renewals->links() }}</div> @endif
    </div>

    @endif

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit renewal' : 'Add renewal'">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                @unless ($clientId)
                    <x-field label="Client" name="form.client_id" required class="md:col-span-2"><select class="input" wire:model="form.client_id"><option value="">Select client…</option>@foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach</select></x-field>
                @endunless
                <x-field label="Type" name="form.type" required><select class="input" wire:model="form.type">@foreach (\App\Models\Renewal::TYPES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select></x-field>
                <x-field label="Name" name="form.name" required><input class="input" type="text" wire:model="form.name" placeholder="e.g. example.com or Annual maintenance"></x-field>
                <x-field label="Provider / registrar" name="form.provider"><input class="input" type="text" wire:model="form.provider"></x-field>
                <x-field label="Renewal date" name="form.renewal_date" hint="Reminders are worked out from this date."><input class="input" type="date" wire:model="form.renewal_date"></x-field>
                <x-field label="Amount ({{ setting('currency', 'INR') }})" name="form.amount"><input class="input" type="number" step="0.01" min="0" wire:model="form.amount"></x-field>
                <x-field label="Repeats" name="form.frequency"><select class="input" wire:model="form.frequency">@foreach (\App\Models\HostingRecord::FREQUENCIES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select></x-field>
                <x-field label="Status" name="form.status"><select class="input" wire:model="form.status">@foreach (\App\Models\Renewal::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select></x-field>
                <x-field label="Notes" name="form.notes" class="md:col-span-2"><textarea class="input" rows="2" wire:model="form.notes"></textarea></x-field>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Save renewal</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($renewing)
        <x-modal :title="'Record Renewal · '.$renewing->name" size="max-w-xl">
            <p class="mb-4 rounded-md bg-stone-50 p-3 text-sm text-stone-700"><strong>{{ $renewing->client->name }}</strong> — renewal due <strong>{{ fmt_date($renewing->renewal_date) }}</strong>. This is saved to the history and the date moves forward.</p>
            <form wire:submit="saveRenewal" class="grid gap-4 md:grid-cols-2">
                <x-field label="Paid on" name="renewal.paid_on" required><input class="input" type="date" wire:model="renewal.paid_on"></x-field>
                <x-field label="Amount paid" name="renewal.amount" required><input class="input" type="number" step="0.01" min="0" wire:model="renewal.amount"></x-field>
                <x-field label="Next renewal date" name="renewal.next_date" required><input class="input" type="date" wire:model="renewal.next_date"></x-field>
                <x-field label="Amount going forward" name="renewal.new_amount" required><input class="input" type="number" step="0.01" min="0" wire:model="renewal.new_amount"></x-field>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Record Renewal</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
