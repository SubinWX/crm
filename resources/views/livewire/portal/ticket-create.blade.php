<div class="mx-auto max-w-3xl">
    <div class="mb-1 text-sm"><a href="{{ route('portal.tickets.index') }}" class="link">← My tickets</a></div>
    <h1 class="text-2xl font-semibold text-stone-900">New ticket</h1>
    <p class="mt-0.5 mb-5 text-sm text-stone-500">You will get a ticket number as soon as you submit.</p>

    <form wire:submit="submit" class="card space-y-5 p-5 sm:p-6">
        <fieldset>
            <legend class="label">What is this about? <span class="text-red-600">*</span></legend>
            <div class="grid gap-2 sm:grid-cols-3">
                @foreach (['support' => 'Something needs fixing or changing', 'new_work' => 'I would like new work done', 'feedback' => 'Share feedback on our work'] as $key => $hint)
                    <label class="cursor-pointer rounded-lg border border-stone-300 bg-white p-3 has-checked:border-brand-500 has-checked:bg-brand-50">
                        <span class="flex items-center gap-2 text-sm font-semibold text-stone-900"><input type="radio" name="type" value="{{ $key }}" wire:model.live="type" class="text-brand-600"> {{ \App\Models\SupportTicket::TYPES[$key] }}</span>
                        <span class="mt-1 block text-xs text-stone-500">{{ $hint }}</span>
                    </label>
                @endforeach
            </div>
            @error('type') <p class="field-error">{{ $message }}</p> @enderror
        </fieldset>

        <x-field label="Subject" name="subject" required>
            <input class="input" type="text" wire:model="subject" maxlength="200" placeholder="{{ $type === 'feedback' ? 'e.g. Feedback on the website launch' : ($type === 'new_work' ? 'e.g. Add an online booking page' : 'e.g. Contact form is not sending emails') }}">
        </x-field>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-field label="Project or service" name="related_work" hint="Which work is this about? e.g. website, SEO, hosting.">
                <input class="input" type="text" wire:model="related_work" maxlength="200">
            </x-field>
            @if ($type === 'feedback')
                <x-field label="Overall rating" name="rating" hint="Optional.">
                    <select class="input" wire:model="rating">
                        <option value="">No rating</option>
                        @foreach ([5 => '5 — Excellent', 4 => '4 — Good', 3 => '3 — Okay', 2 => '2 — Poor', 1 => '1 — Very poor'] as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
            @else
                <x-field label="Priority" name="priority">
                    <select class="input" wire:model="priority">
                        @foreach (\App\Models\SupportTicket::PRIORITIES as $key => $label) <option value="{{ $key }}">{{ $label }}{{ $key === 'urgent' ? ' — site down or business blocked' : '' }}</option> @endforeach
                    </select>
                </x-field>
            @endif
        </div>

        <x-field :label="$type === 'feedback' ? 'Your feedback' : 'Details'" name="description" required hint="{{ $type === 'support' ? 'What happened, where (page link), and what you expected instead.' : '' }}">
            <textarea class="input" rows="7" wire:model="description" maxlength="10000"></textarea>
        </x-field>

        <x-field label="Attachments" name="files" hint="Screenshots or documents. Up to {{ \App\Support\TicketDesk::MAX_ATTACHMENTS }} files, 10 MB each.">
            <input type="file" class="input" multiple wire:model="files" aria-label="Attachments">
            <p wire:loading wire:target="files" class="mt-1 text-xs text-stone-500">Uploading…</p>
            @error('files.*') <p class="field-error">{{ $message }}</p> @enderror
            @if ($files)
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($files as $index => $file)
                        <li class="flex items-center justify-between gap-2 rounded-md bg-stone-50 px-3 py-1.5"><span class="truncate">{{ $file->getClientOriginalName() }}</span><button type="button" class="link text-xs text-red-700" wire:click="removeFile({{ $index }})">Remove</button></li>
                    @endforeach
                </ul>
            @endif
        </x-field>

        <div class="flex justify-end gap-2 border-t border-stone-200 pt-5">
            <a href="{{ route('portal.tickets.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="files,submit">Submit ticket</button>
        </div>
    </form>
</div>
