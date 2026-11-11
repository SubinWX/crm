<div>
    <x-page-header :title="$admin ? 'Equipment' : 'My Equipment'"
                   :subtitle="$admin ? 'Laptops, phones and other items issued to each team member.' : 'Items issued to you. Ask a Super Admin if something here is wrong.'">
        @if ($admin) <button type="button" class="btn-primary" wire:click="create"><x-icon name="plus" /> Add Equipment</button> @endif
    </x-page-header>

    @if ($admin && ($holders->isNotEmpty() || $unassigned))
        <div class="mb-5 flex flex-wrap gap-2" role="group" aria-label="Filter by person">
            <button type="button" wire:click="$set('user', '')" @class(['btn btn-sm border', 'border-ink-900 bg-ink-900 text-white' => $user === '', 'border-stone-300 bg-white text-stone-700 hover:bg-stone-50' => $user !== ''])>Everyone</button>
            @foreach ($holders as $holder)
                <button type="button" wire:key="holder-{{ $holder->id }}" wire:click="$set('user', '{{ $holder->id }}')"
                        @class(['btn btn-sm border', 'border-ink-900 bg-ink-900 text-white' => $user === (string) $holder->id, 'border-stone-300 bg-white text-stone-700 hover:bg-stone-50' => $user !== (string) $holder->id])>
                    <x-avatar :src="$holder->avatarUrl()" :name="$holder->name" size="size-5" class="text-[9px]" /> {{ $holder->name }}
                    <span class="rounded-full bg-stone-200 px-1.5 text-[11px] font-bold text-stone-700">{{ $holder->equipment_count }}</span>
                </button>
            @endforeach
            @if ($unassigned)
                <button type="button" wire:click="$set('user', 'unassigned')" @class(['btn btn-sm border', 'border-ink-900 bg-ink-900 text-white' => $user === 'unassigned', 'border-stone-300 bg-white text-stone-700 hover:bg-stone-50' => $user !== 'unassigned'])>
                    In stock / unassigned <span class="rounded-full bg-stone-200 px-1.5 text-[11px] font-bold text-stone-700">{{ $unassigned }}</span>
                </button>
            @endif
        </div>
    @endif

    <div class="card">
        <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
            <div class="relative w-full md:w-80">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
                <input type="search" class="input pl-9" placeholder="Search item, model, serial number{{ $admin ? ', person' : '' }}…" wire:model.live.debounce.300ms="search" aria-label="Search equipment">
            </div>
            <select class="input w-auto" wire:model.live="type" aria-label="Type">
                <option value="">All types</option>
                @foreach ($types as $t) <option value="{{ $t->id }}">{{ $t->name }}</option> @endforeach
            </select>
            <select class="input w-auto" wire:model.live="condition" aria-label="Condition">
                <option value="">Any condition</option>
                @foreach (\App\Models\Equipment::CONDITIONS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
            @if ($admin && $totalValue > 0) <p class="ml-auto text-sm text-stone-600">Purchase value shown: <strong class="tabular-nums text-stone-900">{{ money($totalValue) }}</strong></p> @endif
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item</th><th>Type</th><th>Serial number</th>@if ($admin)<th>Assigned to</th>@endif<th>Assigned on</th><th>Condition</th>
                        @if ($admin)<th class="num">Purchase cost</th><th class="text-right">Actions</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr wire:key="equipment-{{ $item->id }}">
                            <td class="max-w-80">
                                <p class="font-medium text-stone-900">{{ $item->name }}</p>
                                @if ($item->brand_model) <p class="text-xs text-stone-500">{{ $item->brand_model }}</p> @endif
                                @if ($item->notes) <p class="line-clamp-1 text-xs text-stone-500">{{ $item->notes }}</p> @endif
                            </td>
                            <td>{{ $item->type?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ $item->serial_number ?: '—' }}</td>
                            @if ($admin)
                                <td>
                                    @if ($item->user)
                                        <span class="flex items-center gap-2"><x-avatar :src="$item->user->avatarUrl()" :name="$item->user->name" size="size-7" class="text-[10px]" /> {{ $item->user->name }}@unless ($item->user->is_active) <span class="text-xs text-stone-500">(inactive)</span>@endunless</span>
                                    @else <span class="badge-gray">In stock</span> @endif
                                </td>
                            @endif
                            <td class="whitespace-nowrap">{{ fmt_date($item->assigned_on) }}</td>
                            <td>
                                <span class="{{ ['new' => 'badge-green', 'good' => 'badge-green', 'fair' => 'badge-amber', 'needs_repair' => 'badge-red', 'retired' => 'badge-gray'][$item->condition] ?? 'badge-gray' }}">{{ \App\Models\Equipment::CONDITIONS[$item->condition] ?? $item->condition }}</span>
                            </td>
                            @if ($admin)
                                <td class="num">{{ $item->purchase_cost !== null ? money($item->purchase_cost) : '—' }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $item->id }})">Edit</button>
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $item->id }})" wire:confirm="Delete {{ $item->name }} from the equipment list?">Delete</button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <x-empty :colspan="$admin ? 8 : 5">{{ $admin ? 'No equipment matches. Add the first item to start the register.' : 'Nothing has been issued to you yet.' }}</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($items->hasPages()) <div class="border-t border-stone-200 px-4 py-3">{{ $items->links() }}</div> @endif
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit equipment' : 'Add equipment'">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <x-field label="Item name" name="form.name" required><input class="input" type="text" wire:model="form.name" placeholder="e.g. MacBook Air 13″"></x-field>
                <x-field label="Type" name="form.type_id">
                    <select class="input" wire:model="form.type_id">
                        <option value="">Select…</option>
                        @foreach ($types as $t) <option value="{{ $t->id }}">{{ $t->name }}{{ $t->is_active ? '' : ' (inactive)' }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Brand / model" name="form.brand_model"><input class="input" type="text" wire:model="form.brand_model"></x-field>
                <x-field label="Serial number / asset tag" name="form.serial_number"><input class="input" type="text" wire:model="form.serial_number"></x-field>
                <x-field label="Assigned to" name="form.user_id" hint="Leave empty to keep it in stock.">
                    <select class="input" wire:model="form.user_id">
                        <option value="">In stock / unassigned</option>
                        @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }} · {{ $u->role_label }}{{ $u->is_active ? '' : ' (inactive)' }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Assigned on" name="form.assigned_on"><input class="input" type="date" wire:model="form.assigned_on"></x-field>
                <x-field label="Condition" name="form.condition" required>
                    <select class="input" wire:model="form.condition">
                        @foreach (\App\Models\Equipment::CONDITIONS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Purchase date" name="form.purchase_date"><input class="input" type="date" wire:model="form.purchase_date"></x-field>
                <x-field label="Purchase cost ({{ setting('currency', 'INR') }})" name="form.purchase_cost"><input class="input" type="number" step="0.01" min="0" wire:model="form.purchase_cost"></x-field>
                <x-field label="Notes" name="form.notes" class="md:col-span-2"><textarea class="input" rows="2" wire:model="form.notes" placeholder="Charger included, warranty until…"></textarea></x-field>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary">Save equipment</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
