{{-- Section editor shared by the template editor and the quote editor. --}}
<section class="card">
    <div class="card-header">
        <h2 class="card-title">Sections</h2>
        <div class="flex gap-2">
            @isset($starter) <button type="button" class="btn-ghost btn-sm" wire:click="addStarterSections">Add missing starter sections</button> @endisset
            <button type="button" class="btn-secondary btn-sm" wire:click="addSection"><x-icon name="plus" /> Add section</button>
        </div>
    </div>
    <div class="divide-y divide-stone-200">
        @forelse ($sections as $index => $section)
            <div wire:key="section-{{ $index }}" @class(['p-4', 'bg-stone-50' => $section['is_hidden']])>
                <div class="flex flex-wrap items-center gap-2">
                    <input class="input min-w-56 flex-1 font-semibold" type="text" wire:model="sections.{{ $index }}.title" placeholder="Section title" aria-label="Section title">
                    <label class="flex items-center gap-1.5 text-xs text-stone-600" title="Hidden sections stay here for later but are left out of the PDF"><input type="checkbox" class="rounded border-stone-300" wire:model.live="sections.{{ $index }}.is_hidden"> Hide</label>
                    <button type="button" class="btn-ghost btn-sm" wire:click="moveSection({{ $index }}, -1)" @disabled($index === 0) aria-label="Move section up">↑</button>
                    <button type="button" class="btn-ghost btn-sm" wire:click="moveSection({{ $index }}, 1)" @disabled($index === count($sections) - 1) aria-label="Move section down">↓</button>
                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="removeSection({{ $index }})" wire:confirm="Remove this section?">Remove</button>
                </div>
                @error("sections.{$index}.title") <p class="field-error">{{ $message }}</p> @enderror
                <x-editor class="mt-2" model="sections.{{ $index }}.body" :value="$section['body']" :min-height="$section['is_hidden'] ? '3rem' : '7rem'" placeholder="Text for this section. Use the toolbar for bullets, bold and links." label="Section text" />
                @error("sections.{$index}.body") <p class="field-error">{{ $message }}</p> @enderror
            </div>
        @empty
            <x-empty>No sections yet.</x-empty>
        @endforelse
    </div>
</section>
