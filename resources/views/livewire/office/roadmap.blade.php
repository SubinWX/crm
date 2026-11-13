<div>
    <x-page-header title="Timeline & Change log" subtitle="What has been added to the CRM, what is being worked on now and what is planned next.">
        @if ($canManage) <button type="button" class="btn-primary" wire:click="edit"><x-icon name="plus" /> Add item</button> @endif
    </x-page-header>

    <div class="mb-5 grid grid-cols-3 gap-3">
        <div class="card p-4"><p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">In progress</p><p class="mt-1 text-2xl font-semibold tabular-nums text-stone-900">{{ $inProgress->count() }}</p></div>
        <div class="card p-4"><p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Planned</p><p class="mt-1 text-2xl font-semibold tabular-nums text-stone-900">{{ $planned->count() }}</p></div>
        <div class="card p-4"><p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">Done</p><p class="mt-1 text-2xl font-semibold tabular-nums text-stone-900">{{ $done->flatten(1)->count() }}</p></div>
    </div>

    <div class="grid items-start gap-5 xl:grid-cols-2">
        <div class="space-y-5">
            @foreach ([['In progress', $inProgress, 'badge-amber', 'Mark done', 'Nothing is being worked on right now.'], ['Planned', $planned, 'badge-gray', 'Start', 'Nothing is planned yet.']] as [$heading, $list, $badge, $action, $empty])
                <div class="card">
                    <div class="card-header"><h2 class="card-title">{{ $heading }}</h2><span class="text-xs text-stone-500">{{ $list->count() }} {{ $list->count() === 1 ? 'item' : 'items' }}</span></div>
                    <ul class="divide-y divide-stone-100">
                        @forelse ($list as $item)
                            <li class="flex items-start gap-3 px-4 py-3" wire:key="item-{{ $item->id }}">
                                <span class="mt-0.5 w-6 shrink-0 text-right text-xs tabular-nums text-stone-400">{{ $loop->iteration }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-stone-900">{{ $item->title }} @if ($item->area) <span class="{{ $badge }} ml-1">{{ $item->area }}</span> @endif</p>
                                    @if ($item->details) <p class="mt-0.5 text-sm text-stone-600">{{ $item->details }}</p> @endif
                                    @if ($item->happened_on) <p class="mt-0.5 text-xs text-stone-500">Target: {{ fmt_date($item->happened_on) }}</p> @endif
                                </div>
                                @if ($canManage)
                                    <div class="flex shrink-0 gap-1">
                                        <button type="button" class="btn-ghost btn-sm" wire:click="advance({{ $item->id }})">{{ $action }}</button>
                                        <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $item->id }})">Edit</button>
                                    </div>
                                @endif
                            </li>
                        @empty
                            <li class="px-4 py-8 text-center text-sm text-stone-500">{{ $empty }}</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Change log</h2><span class="text-xs text-stone-500">Newest first</span></div>
            <div class="px-4 py-4 sm:px-5">
                @forelse ($done as $day => $items)
                    <div class="relative border-l-2 border-stone-200 pb-5 pl-5 last:pb-0">
                        <span class="absolute top-1 -left-[7px] size-3 rounded-full border-2 border-white bg-brand-600"></span>
                        <p class="text-xs font-semibold tracking-wide text-stone-500 uppercase">{{ $day }}</p>
                        <ul class="mt-2 space-y-2.5">
                            @foreach ($items as $item)
                                <li class="flex items-start gap-3" wire:key="done-{{ $item->id }}">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-stone-900">{{ $item->title }} @if ($item->area) <span class="badge-green ml-1">{{ $item->area }}</span> @endif</p>
                                        @if ($item->details) <p class="mt-0.5 text-sm text-stone-600">{{ $item->details }}</p> @endif
                                    </div>
                                    @if ($canManage) <button type="button" class="btn-ghost btn-sm shrink-0" wire:click="edit({{ $item->id }})">Edit</button> @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-stone-500">Nothing has been marked done yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit item' : 'Add item'" size="max-w-xl">
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <x-field label="Title" name="form.title" required class="sm:col-span-2"><input class="input" type="text" wire:model="form.title" placeholder="e.g. Payroll and salary slips"></x-field>
                <x-field label="Details" name="form.details" class="sm:col-span-2"><textarea class="input" rows="3" wire:model="form.details"></textarea></x-field>
                <x-field label="Status" name="form.status" required>
                    <select class="input" wire:model="form.status">
                        @foreach (\App\Models\RoadmapItem::STATUSES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Area" name="form.area"><input class="input" type="text" wire:model="form.area" placeholder="e.g. Finance, Team"></x-field>
                <x-field label="Date" name="form.happened_on" hint="Done: the day it was finished. Otherwise the target date."><input class="input" type="date" wire:model="form.happened_on"></x-field>
                <x-field label="Priority" name="form.sort_order" hint="1 is at the top of the list."><input class="input" type="number" min="0" wire:model="form.sort_order"></x-field>
                <div class="flex items-center justify-between gap-2 sm:col-span-2">
                    @if ($editingId) <button type="button" class="btn-ghost text-red-700" wire:click="delete({{ $editingId }}); closeModal()" wire:confirm="Delete this item?">Delete</button> @else <span></span> @endif
                    <div class="flex gap-2">
                        <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                        <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save</button>
                    </div>
                </div>
            </form>
        </x-modal>
    @endif
</div>
