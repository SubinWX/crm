<div>
    <x-page-header title="Meeting Notes" :subtitle="$manager ? 'Notes of reviews and one-to-one meetings with the team, and the notes shared with you.' : 'Notes of meetings held with you, such as reviews and one-to-ones.'">
        @if ($manager)
            <select class="input w-auto" wire:model.live="person" aria-label="Person">
                <option value="">Everyone</option>
                @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
            </select>
            <button type="button" class="btn-primary" wire:click="edit"><x-icon name="plus" /> Add meeting note</button>
        @endif
    </x-page-header>

    <div class="space-y-3">
        @forelse ($notes as $note)
            @php $mine = in_array($note->user_id, $managedIds, true); @endphp
            <div class="card" wire:key="note-{{ $note->id }}">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-stone-100 px-4 py-3 sm:px-5">
                    <p class="min-w-0 flex-1 text-sm font-semibold text-stone-900">{{ $note->title }}
                        @if ($manager) <span class="font-normal text-stone-500">· with {{ $note->user_id === auth()->id() ? 'you' : ($note->user?->name ?? 'someone') }}</span> @endif</p>
                    @unless ($note->shared) <span class="badge-amber" title="{{ $note->user?->name }} does not see this note">Managers only</span> @endunless
                    <span class="text-xs text-stone-500">{{ fmt_date($note->held_on) }}@if ($note->author) · written by {{ $note->author->name }}@endif</span>
                    @if ($mine)
                        <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $note->id }})">Edit</button>
                        <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $note->id }})" wire:confirm="Delete this meeting note? This cannot be undone.">Delete</button>
                    @endif
                </div>
                <div class="rich px-4 py-4 text-sm text-stone-700 sm:px-5">{!! \App\Support\RichText::render($note->notes, autolink: true) !!}</div>
            </div>
        @empty
            <div class="card p-8 text-center text-sm text-stone-500">No meeting notes yet.@unless ($manager) They appear here when a manager adds one.@endunless</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $notes->links() }}</div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit meeting note' : 'Add meeting note'" size="max-w-3xl">
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-[14rem_1fr_11rem]">
                    <x-field label="Meeting with" name="form.user_id" required>
                        <select class="input" wire:model="form.user_id">
                            <option value="">Select…</option>
                            @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
                        </select>
                    </x-field>
                    <x-field label="Title" name="form.title" required><input class="input" type="text" wire:model="form.title" placeholder="e.g. Quarterly review"></x-field>
                    <x-field label="Meeting date" name="form.held_on" required><input class="input" type="date" wire:model="form.held_on"></x-field>
                </div>
                <x-field label="Notes" name="form.notes" required>
                    <x-editor model="form.notes" :value="$form['notes'] ?? ''" min-height="12rem" label="Meeting notes" />
                </x-field>
                <label class="flex items-start gap-2 text-sm text-stone-700">
                    <input type="checkbox" class="mt-0.5" wire:model="form.shared">
                    <span>The person can read this note. <span class="text-stone-500">Untick to keep it for managers only.</span></span>
                </label>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save note</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
