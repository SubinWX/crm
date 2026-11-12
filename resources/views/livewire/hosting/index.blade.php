<div>
    <div class="card">
        <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
            @if ($clientId)
                <h2 class="card-title mr-auto">Hosting records</h2>
                @if ($this->canEdit()) <button type="button" class="btn-primary btn-sm" wire:click="create"><x-icon name="plus" /> Add hosting record</button> @endif
            @else
                <div class="relative w-full md:w-72">
                    <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
                    <input type="search" class="input pl-9" placeholder="Search client, domain, provider…" wire:model.live.debounce.300ms="search" aria-label="Search hosting">
                </div>
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="Due date filter">
                    @foreach (['' => 'All', 'overdue' => 'Overdue', 'week' => 'Due within 7 days', 'two_months' => 'Due within 2 months'] as $key => $label)
                        <button type="button" wire:click="$set('due', '{{ $key }}')" aria-pressed="{{ $due === $key ? 'true' : 'false' }}"
                                @class(['btn btn-sm border', 'border-ink-900 bg-ink-900 text-white' => $due === $key, 'border-stone-300 bg-white text-stone-700 hover:bg-stone-50' => $due !== $key])>
                            {{ $label }}
                            @if ($key !== '' && $counts)
                                <span @class(['rounded-full px-1.5 text-[11px] font-bold', 'bg-red-600 text-white' => $counts[$key] > 0, 'bg-stone-200 text-stone-600' => $counts[$key] === 0])>{{ $counts[$key] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
                <label class="ml-auto flex items-center gap-2 text-sm text-stone-600">
                    <input type="checkbox" class="rounded border-stone-300" wire:model.live="includeInactive"> Include suspended / cancelled
                </label>
                @if ($this->canEdit()) <button type="button" class="btn-primary btn-sm" wire:click="create"><x-icon name="plus" /> Add hosting record</button> @endif
            @endif
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        @unless ($clientId) <th>Client</th> @endunless
                        <th>Domain</th><th>Server Type</th><th>Provider / plan</th><th>Billing start → end</th>
                        <th>Next payment</th><th class="num">Renewal amount</th><th>Days until due</th><th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        @php $urgent = in_array($record->urgency(), ['overdue', 'week', 'two_months']); @endphp
                        <tr wire:key="host-{{ $record->id }}">
                            @unless ($clientId)
                                <td><a class="link text-stone-900" href="{{ route('clients.show', ['client' => $record->client_id, 'tab' => 'renewals']) }}">{{ $record->client->name }}</a></td>
                            @endunless
                            <td>
                                <span class="font-medium text-stone-900">{{ $record->domain }}</span>
                                @if ($record->status !== 'active') <x-status :value="$record->status" /> @endif
                            </td>
                            <td>{{ $record->serverType?->name ?? '—' }}</td>
                            <td>{{ $record->serverProvider?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap"><div>{{ fmt_date($record->billing_start) }}</div><div class="text-stone-500">→ {{ fmt_date($record->billing_end) }}</div></td>
                            <td @class(['whitespace-nowrap', 'font-semibold text-red-700' => $urgent])>{{ fmt_date($record->next_payment_date) }}</td>
                            <td class="num">{{ money($record->renewal_amount) }}</td>
                            <td><x-urgency :record="$record" /></td>
                            <td class="text-right whitespace-nowrap">
                                @can("clients.manage")
                                    @if ($record->status === "active" && $record->next_payment_date)
                                        <div><button type="button" class="btn-secondary btn-sm" wire:click="startRenewal({{ $record->id }})">Record Renewal</button></div>
                                    @endif
                                @endcan
                                @if ($record->next_payment_date && $record->client)
                                    <x-whatsapp-reminder :client="$record->client" kind="hosting" send="sendWhatsApp({{ $record->id }})" :values="['item' => $record->domain ?: 'your website', 'date' => fmt_date($record->next_payment_date), 'amount' => $record->renewal_amount !== null ? money($record->renewal_amount) : '—']" />
                                @endif
                                <button type="button" class="btn-ghost btn-sm" wire:click="toggle({{ $record->id }})" aria-expanded="{{ $expandedId === $record->id ? "true" : "false" }}">History</button>
                                @if ($this->canEdit())
                                    <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $record->id }})">Edit</button>
                                @endif
                            </td>
                        </tr>
                        @if ($expandedId === $record->id && $expanded)
                            <tr wire:key="host-detail-{{ $record->id }}">
                                <td colspan="{{ $clientId ? 8 : 9 }}" class="!bg-stone-50 !p-0">
                                    <div class="grid gap-6 p-4 lg:grid-cols-3">
                                        <div class="lg:col-span-2">
                                            <p class="mb-2 text-xs font-semibold tracking-wide text-stone-500 uppercase">Renewal history</p>
                                            @if ($expanded->renewals->isEmpty())
                                                <p class="text-sm text-stone-500">No renewals recorded yet.</p>
                                            @else
                                                <table class="w-full text-sm">
                                                    <thead><tr class="text-left text-xs text-stone-500"><th class="py-1 pr-4 font-semibold">Billing period</th><th class="py-1 pr-4 font-semibold">Was due</th><th class="py-1 pr-4 font-semibold">Paid on</th><th class="py-1 pr-4 text-right font-semibold">Amount</th><th class="py-1 font-semibold">Recorded by</th></tr></thead>
                                                    <tbody>
                                                        @foreach ($expanded->renewals as $renewal)
                                                            <tr class="border-t border-stone-200">
                                                                <td class="py-1.5 pr-4 whitespace-nowrap">{{ fmt_date($renewal->period_start) }} → {{ fmt_date($renewal->period_end) }}</td>
                                                                <td class="py-1.5 pr-4 whitespace-nowrap">{{ fmt_date($renewal->payment_due_date) }}</td>
                                                                <td class="py-1.5 pr-4 whitespace-nowrap">{{ fmt_date($renewal->paid_on) }}</td>
                                                                <td class="py-1.5 pr-4 text-right tabular-nums">{{ money($renewal->amount) }}</td>
                                                                <td class="py-1.5">{{ $renewal->recorder?->name ?? '—' }}@if ($renewal->notes) <span class="text-stone-500">· {{ $renewal->notes }}</span>@endif</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="mb-2 text-xs font-semibold tracking-wide text-stone-500 uppercase">Reminders for the current payment date</p>
                                            @php $reminders = $this->reminderStatus($expanded); @endphp
                                            @forelse ($reminders as $reminder)
                                                <p class="flex items-center justify-between gap-3 py-1 text-sm">
                                                    <span>{{ $reminder['label'] }} · {{ fmt_date($reminder['date']) }}</span>
                                                    <span class="{{ $reminder['sent'] ? 'badge-green' : 'badge-gray' }}">{{ $reminder['sent'] ? 'Sent' : 'Scheduled' }}</span>
                                                </p>
                                            @empty
                                                <p class="text-sm text-stone-500">No reminders: only active <strong>Paid</strong> records with a payment date are reminded.</p>
                                            @endforelse
                                            @if ($expanded->notes) <p class="mt-3 text-sm whitespace-pre-line text-stone-600">{{ $expanded->notes }}</p> @endif
                                            @can('clients.manage')
                                                <button type="button" class="link mt-3 text-xs text-red-700" wire:click="delete({{ $record->id }})"
                                                        wire:confirm="Delete the hosting record for {{ $record->domain }} and its renewal history?">Delete this hosting record</button>
                                            @endcan
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <x-empty :colspan="$clientId ? 8 : 9">{{ $clientId ? 'No hosting records for this client yet.' : 'No paid hosting records match these filters.' }}</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($records->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $records->links() }}</div> @endif
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit hosting record' : 'Add hosting record'">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                @unless ($clientId)
                    <x-field label="Client" name="form.client_id" required class="md:col-span-2">
                        <select class="input" wire:model="form.client_id" @disabled($editingId)>
                            <option value="">Select client…</option>
                            @foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
                        </select>
                    </x-field>
                @endunless
                <x-field label="Hosted website / domain" name="form.domain" required><input class="input" type="text" wire:model="form.domain" placeholder="example.com"></x-field>
                <x-field label="Status" name="form.status">
                    <select class="input" wire:model="form.status">
                        @foreach (\App\Models\HostingRecord::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Server Type" name="form.server_type_id" required hint="The hosting arrangement.">
                    <select class="input" wire:model="form.server_type_id">
                        <option value="">Select…</option>
                        @foreach ($serverTypes as $o) <option value="{{ $o->id }}">{{ $o->name }}{{ $o->is_active ? '' : ' (inactive)' }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Server Chosen" name="form.server_provider_id" hint="The actual provider or plan.">
                    <select class="input" wire:model="form.server_provider_id">
                        <option value="">Select…</option>
                        @foreach ($providers as $o) <option value="{{ $o->id }}">{{ $o->name }}{{ $o->is_active ? '' : ' (inactive)' }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Billing start date" name="form.billing_start"><input class="input" type="date" wire:model="form.billing_start"></x-field>
                <x-field label="Billing end date" name="form.billing_end"><input class="input" type="date" wire:model="form.billing_end"></x-field>
                <x-field label="Next server payment date" name="form.next_payment_date" hint="Reminders are recalculated when this changes."><input class="input" type="date" wire:model="form.next_payment_date"></x-field>
                <x-field label="Renewal amount ({{ setting('currency', 'INR') }})" name="form.renewal_amount"><input class="input" type="number" step="0.01" min="0" wire:model="form.renewal_amount"></x-field>
                <x-field label="Billing frequency" name="form.billing_frequency">
                    <select class="input" wire:model="form.billing_frequency">
                        @foreach (\App\Models\HostingRecord::FREQUENCIES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Notes" name="form.notes" class="md:col-span-2"><textarea class="input" rows="2" wire:model="form.notes"></textarea></x-field>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Save hosting record</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if ($renewing)
        <x-modal :title="'Record Renewal · '.$renewing->domain">
            <div class="mb-4 rounded-md bg-stone-50 p-3 text-sm text-stone-700">
                <p><strong>{{ $renewing->client->name }}</strong> — completing billing period
                    <strong>{{ fmt_date($renewing->billing_start) }} → {{ fmt_date($renewing->billing_end) }}</strong>,
                    payment due <strong>{{ fmt_date($renewing->next_payment_date) }}</strong>.</p>
                <p class="mt-1 text-xs text-stone-500">This period is saved to the renewal history and the record moves to the next period below.</p>
            </div>
            <form wire:submit="saveRenewal" class="grid gap-4 md:grid-cols-2">
                <x-field label="Paid on" name="renewal.paid_on" required><input class="input" type="date" wire:model="renewal.paid_on"></x-field>
                <x-field label="Amount paid ({{ setting('currency', 'INR') }})" name="renewal.amount" required><input class="input" type="number" step="0.01" min="0" wire:model="renewal.amount"></x-field>
                <x-field label="New billing start" name="renewal.new_billing_start"><input class="input" type="date" wire:model="renewal.new_billing_start"></x-field>
                <x-field label="New billing end" name="renewal.new_billing_end"><input class="input" type="date" wire:model="renewal.new_billing_end"></x-field>
                <x-field label="Next payment date" name="renewal.next_payment_date" required hint="New reminders are scheduled from this date."><input class="input" type="date" wire:model="renewal.next_payment_date"></x-field>
                <x-field label="Renewal amount going forward" name="renewal.new_renewal_amount" required><input class="input" type="number" step="0.01" min="0" wire:model="renewal.new_renewal_amount"></x-field>
                <x-field label="Notes" name="renewal.notes" class="md:col-span-2"><input class="input" type="text" wire:model="renewal.notes"></x-field>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Record Renewal</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
