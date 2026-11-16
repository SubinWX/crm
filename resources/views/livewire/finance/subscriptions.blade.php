<div>
    <x-page-header title="Subscriptions" subtitle="The tools and services the company pays for regularly: what they cost, when they renew and who looks after them.">
        <button type="button" class="btn-primary" wire:click="edit"><x-icon name="plus" /> Add subscription</button>
    </x-page-header>

    <div class="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
        <x-stat label="Cost per month" :value="money($monthly)" :hint="$activeCount.' active '.str('subscription')->plural($activeCount)" />
        <x-stat label="Cost per year" :value="money($yearly)" hint="At today’s prices" />
        <x-stat label="Renewing in 30 days" :value="$renewingSoon" hint="Including any already overdue" :tone="$renewingSoon ? 'danger' : 'default'" />
        <x-stat label="Active subscriptions" :value="$activeCount" hint="Cancelled ones are not counted" />
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <select class="input w-auto" wire:model.live="status" aria-label="Status">
            <option value="active">Active</option><option value="cancelled">Cancelled</option><option value="all">All</option>
        </select>
        <div class="relative ml-auto w-full sm:w-72">
            <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
            <input class="input pl-9" type="search" placeholder="Search" wire:model.live.debounce.300ms="search" aria-label="Search subscriptions">
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Category</th><th class="num">Cost</th><th class="num">Per month</th><th>Renews on</th><th>Looked after by</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($items as $s)
                        @php $days = days_until($s->renews_on); @endphp
                        <tr wire:key="sub-{{ $s->id }}" @class(['opacity-60' => $s->status === 'cancelled'])>
                            <td>
                                <span class="font-medium text-stone-900">{{ $s->name }}</span> @if ($s->status === 'cancelled') <span class="badge-gray">Cancelled</span> @endif
                                <p class="text-xs text-stone-500">@if ($s->seats) {{ $s->seats }} {{ str('seat')->plural($s->seats) }} @endif @if ($s->url) <a href="{{ $s->url }}" target="_blank" rel="noopener noreferrer" class="link">Open site</a> @endif</p>
                            </td>
                            <td>{{ $s->category ?: '—' }}</td>
                            <td class="num">{{ money($s->cost) }} <span class="text-xs text-stone-500">/ {{ ['monthly' => 'month', 'quarterly' => '3 months', 'yearly' => 'year'][$s->billing_cycle] ?? $s->billing_cycle }}</span></td>
                            <td class="num">{{ money($s->monthlyCost()) }}</td>
                            <td class="whitespace-nowrap">
                                @if ($s->renews_on)
                                    {{ fmt_date($s->renews_on) }}
                                    @if ($s->status === 'active' && $days !== null && $days <= 30) <span class="{{ $days < 0 ? 'badge-red' : 'badge-amber' }} ml-1">{{ $days < 0 ? abs($days).' '.str('day')->plural(abs($days)).' overdue' : ($days === 0 ? 'Today' : 'in '.$days.' '.str('day')->plural($days)) }}</span> @endif
                                @else — @endif
                            </td>
                            <td>{{ $s->owner?->name ?? '—' }}</td>
                            <td class="text-right whitespace-nowrap">
                                @if ($s->status === 'active' && $s->renews_on) <button type="button" class="btn-ghost btn-sm" wire:click="renewed({{ $s->id }})" wire:confirm="Mark {{ $s->name }} as paid and move its renewal date forward?">Mark renewed</button> @endif
                                <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $s->id }})">Edit</button>
                                <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $s->id }})" wire:confirm="Delete {{ $s->name }}? Use Cancelled instead if you want to keep the record.">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <x-empty :colspan="7">No subscriptions here yet. Press <strong>Add subscription</strong> to list the first tool you pay for.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit subscription' : 'Add subscription'">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <x-field label="Name" name="form.name" required><input class="input" type="text" wire:model="form.name" placeholder="e.g. Google Workspace, Figma"></x-field>
                <x-field label="Category" name="form.category"><input class="input" type="text" wire:model="form.category" placeholder="e.g. Design, Email, Hosting"></x-field>
                <x-field label="Cost per payment" name="form.cost" required><input class="input" type="number" step="0.01" min="0" wire:model="form.cost"></x-field>
                <x-field label="Paid" name="form.billing_cycle" required>
                    <select class="input" wire:model="form.billing_cycle">@foreach (\App\Models\SoftwareSubscription::CYCLES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select>
                </x-field>
                <x-field label="Next renewal date" name="form.renews_on"><input class="input" type="date" wire:model="form.renews_on"></x-field>
                <x-field label="Looked after by" name="form.owner_id">
                    <select class="input" wire:model="form.owner_id"><option value="">Nobody yet</option>@foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach</select>
                </x-field>
                <x-field label="Seats / licences" name="form.seats"><input class="input" type="number" min="1" wire:model="form.seats"></x-field>
                <x-field label="Status" name="form.status" required>
                    <select class="input" wire:model="form.status">@foreach (\App\Models\SoftwareSubscription::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select>
                </x-field>
                <x-field label="Website" name="form.url" class="md:col-span-2"><input class="input" type="text" wire:model="form.url" placeholder="https://"></x-field>
                <x-field label="Notes" name="form.notes" class="md:col-span-2" hint="Do not store passwords here."><textarea class="input" rows="2" wire:model="form.notes"></textarea></x-field>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
