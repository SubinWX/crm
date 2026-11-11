{{-- Line-item editor shared by the template editor and the quote editor. $currency is optional. --}}
<section class="card">
    <div class="card-header"><h2 class="card-title">Line items</h2><button type="button" class="btn-secondary btn-sm" wire:click="addItem"><x-icon name="plus" /> Add line</button></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th class="w-full">Service / product</th><th>Qty</th><th>Unit price</th><th>Billing</th><th class="num">Amount</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
                @forelse ($items as $index => $item)
                    <tr wire:key="item-{{ $index }}">
                        <td class="min-w-72">
                            <input class="input" type="text" wire:model="items.{{ $index }}.name" placeholder="e.g. Website design and build" aria-label="Item name">
                            @error("items.{$index}.name") <p class="field-error">{{ $message }}</p> @enderror
                            <input class="input mt-1.5 text-xs" type="text" wire:model="items.{{ $index }}.description" placeholder="Short description (optional)" aria-label="Item description">
                        </td>
                        <td>
                            <input class="input w-20 text-right" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="items.{{ $index }}.quantity" aria-label="Quantity">
                            @error("items.{$index}.quantity") <p class="field-error">{{ $message }}</p> @enderror
                        </td>
                        <td>
                            <input class="input w-32 text-right" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="items.{{ $index }}.unit_price" aria-label="Unit price">
                            @error("items.{$index}.unit_price") <p class="field-error">{{ $message }}</p> @enderror
                        </td>
                        <td>
                            <select class="input w-32" wire:model.live="items.{{ $index }}.billing_frequency" aria-label="Billing frequency">
                                @foreach (\App\Models\Quote::FREQUENCIES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                            </select>
                        </td>
                        <td class="num">{{ money((float) ($item['quantity'] ?: 0) * (float) ($item['unit_price'] ?: 0), $currency ?? null) }}</td>
                        <td class="whitespace-nowrap">
                            <button type="button" class="btn-ghost btn-sm" wire:click="moveItem({{ $index }}, -1)" @disabled($index === 0) aria-label="Move line up">↑</button>
                            <button type="button" class="btn-ghost btn-sm" wire:click="moveItem({{ $index }}, 1)" @disabled($index === count($items) - 1) aria-label="Move line down">↓</button>
                            <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="removeItem({{ $index }})" aria-label="Remove line"><x-icon name="x" /></button>
                        </td>
                    </tr>
                @empty
                    <x-empty colspan="6">No line items yet.</x-empty>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
