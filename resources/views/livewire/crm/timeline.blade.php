<section class="card">
    <div class="card-header"><h2 class="card-title">Contact log</h2><span class="text-xs text-stone-500">Calls, emails, WhatsApp, meetings and notes</span></div>
    <form wire:submit="addLog" class="grid gap-3 border-b border-stone-200 p-4 sm:grid-cols-[10rem_14rem_minmax(0,1fr)_auto] sm:items-start">
        <select class="input" wire:model="log.type" aria-label="Type">@foreach (\App\Models\Interaction::TYPES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach</select>
        <input class="input" type="datetime-local" wire:model="log.happened_at" aria-label="When">
        <div>
            <textarea class="input" rows="1" wire:model="log.summary" placeholder="What was said or agreed?" aria-label="Summary"></textarea>
            @error('log.summary') <p class="field-error">{{ $message }}</p> @enderror
            @error('log.happened_at') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-secondary"><x-icon name="plus" /> Log it</button>
    </form>
    <div class="max-h-[28rem] overflow-y-auto">
        @forelse ($entries as $entry)
            <div wire:key="log-{{ $entry->id }}" class="border-b border-stone-100 px-4 py-3 text-sm last:border-b-0">
                <p class="flex flex-wrap items-center gap-2">
                    <span class="badge-gray">{{ \App\Models\Interaction::TYPES[$entry->type] ?? $entry->type }}</span>
                    <span class="text-xs text-stone-500">{{ fmt_datetime($entry->happened_at) }} · {{ $entry->user?->name ?? '—' }}</span>
                    @if ($entry->user_id === auth()->id() || auth()->user()->can('admin'))
                        <button type="button" class="ml-auto text-xs text-stone-400 hover:text-red-700" wire:click="deleteLog({{ $entry->id }})" wire:confirm="Delete this log entry?">Delete</button>
                    @endif
                </p>
                <p class="mt-1 whitespace-pre-line text-stone-800">{{ $entry->summary }}</p>
            </div>
        @empty
            <x-empty>Nothing logged yet.</x-empty>
        @endforelse
    </div>
</section>
