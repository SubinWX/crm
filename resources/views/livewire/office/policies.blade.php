<div>
    <x-page-header title="Rules & Policies" subtitle="The company’s rules and policies. They are the same for everyone.">
        @if ($canManage) <button type="button" class="btn-primary" wire:click="edit"><x-icon name="plus" /> Add policy</button> @endif
    </x-page-header>

    <div class="space-y-3">
        @forelse ($policies as $policy)
            <div class="card" wire:key="policy-{{ $policy->id }}" x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }">
                <div class="flex items-center gap-3 px-4 py-3 sm:px-5">
                    <button type="button" class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 text-left" @click="open = !open" :aria-expanded="open">
                        <x-icon name="chevron" class="size-4 text-stone-400 transition-transform" ::class="open && 'rotate-180'" />
                        <span class="truncate text-sm font-semibold text-stone-900">{{ $policy->title }}</span>
                    </button>
                    <span class="hidden text-xs text-stone-500 sm:inline">Updated {{ fmt_date($policy->updated_at) }}</span>
                    @if ($canManage)
                        <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $policy->id }})">Edit</button>
                        <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $policy->id }})" wire:confirm="Delete the policy “{{ $policy->title }}”? This cannot be undone.">Delete</button>
                    @endif
                </div>
                <div x-show="open" x-collapse @unless ($loop->first) x-cloak @endunless>
                    <div class="rich border-t border-stone-100 px-4 py-4 text-sm text-stone-700 sm:px-5">{!! \App\Support\RichText::render($policy->body, autolink: true) !!}</div>
                </div>
            </div>
        @empty
            <div class="card p-8 text-center text-sm text-stone-500">No rules or policies have been added yet.@if ($canManage) Press <strong>Add policy</strong> to write the first one.@endif</div>
        @endforelse
    </div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit policy' : 'Add policy'" size="max-w-3xl">
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
                    <x-field label="Title" name="form.title" required><input class="input" type="text" wire:model="form.title" placeholder="e.g. Working hours, Leave policy"></x-field>
                    <x-field label="Position" name="form.sort_order" hint="1 shows first."><input class="input" type="number" min="0" wire:model="form.sort_order"></x-field>
                </div>
                <x-field label="Text" name="form.body" required>
                    <x-editor model="form.body" :value="$form['body'] ?? ''" min-height="14rem" label="Policy text" />
                </x-field>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save policy</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
