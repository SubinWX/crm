<div>
    <x-page-header title="Announcements" subtitle="Notices from the company to everyone. Current ones also show on the dashboard.">
        @if ($canManage) <button type="button" class="btn-primary" wire:click="edit"><x-icon name="plus" /> New announcement</button> @endif
    </x-page-header>

    <div class="space-y-3">
        @forelse ($announcements as $a)
            @php $expired = $a->expires_on && $a->expires_on->toDateString() < $today; @endphp
            <article class="card" wire:key="ann-{{ $a->id }}">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-stone-100 px-4 py-3 sm:px-5">
                    <h2 class="min-w-0 flex-1 text-sm font-semibold text-stone-900">{{ $a->title }}</h2>
                    @if ($a->pinned) <span class="badge-blue">Pinned</span> @endif
                    @if ($expired) <span class="badge-gray">Ended</span> @endif
                    <span class="text-xs text-stone-500">{{ fmt_date($a->created_at) }}@if ($a->author) · {{ $a->author->name }}@endif @if ($a->expires_on && ! $expired) · until {{ fmt_date($a->expires_on) }}@endif</span>
                    @if ($canManage)
                        <button type="button" class="btn-ghost btn-sm" wire:click="edit({{ $a->id }})">Edit</button>
                        <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $a->id }})" wire:confirm="Delete this announcement?">Delete</button>
                    @endif
                </div>
                @if (filled($a->body)) <div class="rich px-4 py-4 text-sm text-stone-700 sm:px-5">{!! \App\Support\RichText::render($a->body, autolink: true) !!}</div> @endif
            </article>
        @empty
            <div class="card p-8 text-center text-sm text-stone-500">No announcements yet.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $announcements->links() }}</div>

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit announcement' : 'New announcement'" size="max-w-3xl">
            <form wire:submit="save" class="space-y-4">
                <x-field label="Title" name="form.title" required><input class="input" type="text" wire:model="form.title" placeholder="e.g. Office closed on Friday"></x-field>
                <x-field label="Text" name="form.body"><x-editor model="form.body" :value="$form['body'] ?? ''" min-height="10rem" label="Announcement text" /></x-field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Show on the dashboard until" name="form.expires_on" hint="Leave empty to keep it there until you delete it."><input class="input" type="date" wire:model="form.expires_on"></x-field>
                    <label class="flex items-center gap-2 self-end pb-2 text-sm text-stone-700"><input type="checkbox" wire:model="form.pinned"> Pin to the top</label>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
