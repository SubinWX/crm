<div class="card mb-5 flex flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
    <div>
        <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Hours not billed yet</p>
        <p class="text-lg font-semibold text-stone-900">{{ \App\Models\TimeEntry::format($unbilled) }} <span class="text-sm font-normal text-stone-500">billable · {{ \App\Models\TimeEntry::format($billed) }} already on invoices</span></p>
    </div>
    <span class="flex-1"></span>
    <button type="button" class="btn-primary" wire:click="open" @disabled($unbilled === 0)><x-icon name="cash" /> Bill hours</button>

    @if ($showForm)
        <x-modal title="Bill tracked hours" size="max-w-xl">
            <form wire:submit="createInvoice" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-field label="From" name="from"><input class="input" type="date" wire:model.live="from"></x-field>
                    <x-field label="To" name="to"><input class="input" type="date" wire:model.live="to"></x-field>
                    <x-field label="Rate per hour" name="rate" required><input class="input" type="number" step="0.01" min="0" wire:model.live.debounce.400ms="rate" placeholder="e.g. 1500"></x-field>
                </div>
                <div class="rounded-md border border-stone-200">
                    @forelse ($byPerson as $row)
                        <div class="flex items-center justify-between border-b border-stone-100 px-3 py-2 text-sm last:border-b-0"><span>{{ $row->name }}</span><span class="tabular-nums text-stone-700">{{ \App\Models\TimeEntry::format((int) $row->minutes) }}</span></div>
                    @empty
                        <p class="px-3 py-6 text-center text-sm text-stone-500">No unbilled billable hours in these dates.</p>
                    @endforelse
                    @if ($byPerson->isNotEmpty())
                        <div class="flex items-center justify-between bg-stone-50 px-3 py-2 text-sm font-semibold text-stone-900"><span>Total · {{ \App\Models\TimeEntry::format($minutes) }}</span><span>{{ money($amount) }} <span class="text-xs font-normal text-stone-500">before tax</span></span></div>
                    @endif
                </div>
                <x-field label="Lines on the invoice" name="lines">
                    <select class="input" wire:model="lines"><option value="total">One line for all the hours</option><option value="person">One line per person</option></select>
                </x-field>
                <p class="text-xs text-stone-500">This creates a draft invoice and opens it so you can adjust it before sending. The hours are marked as billed and will not be offered again; deleting the invoice frees them.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" @disabled($byPerson->isEmpty())>Create draft invoice</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
