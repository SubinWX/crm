<div>
    <div class="mb-1 text-sm"><a href="{{ route('quote-templates.index') }}" class="link">← Quote Templates</a></div>
    <x-page-header :title="$template ? 'Edit template' : 'New template'" subtitle="Sections and prices here are the starting point. Staff tailor them on each quote.">
        <a href="{{ route('quote-templates.index') }}" class="btn-secondary">Cancel</a>
    </x-page-header>

    <form wire:submit="save" class="space-y-5">
        <section class="card grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
            <x-field label="Template name" name="name" required class="xl:col-span-2"><input class="input" type="text" wire:model="name" placeholder="e.g. Website design and development"></x-field>
            <x-field label="Product / service" name="category"><input class="input" type="text" wire:model="category" list="template-categories" placeholder="e.g. Web Development"></x-field>
            <x-field label="Quote valid for (days)" name="validity_days" required><input class="input" type="number" min="1" max="365" wire:model="validity_days"></x-field>
            <datalist id="template-categories">@foreach (['Web Development', 'Hosting', 'Website Maintenance', 'SEO', 'Social Media Management', 'Digital Marketing'] as $c) <option value="{{ $c }}"> @endforeach</datalist>
            <x-field label="Internal description" name="description" class="md:col-span-2 xl:col-span-4"><input class="input" type="text" wire:model="description" placeholder="When to use this template"></x-field>
        </section>

        @include('livewire.quotes._sections', ['starter' => true])
        @include('livewire.quotes._items')

        <section class="card">
            <div class="card-header"><h2 class="card-title">Reference attachments</h2><span class="text-xs text-stone-500">Your source proposal, portfolio PDFs, case studies. Kept with the template for staff to open and copy from.</span></div>
            <div class="space-y-3 p-4">
                @foreach ($files as $file)
                    <p wire:key="tf-{{ $file->id }}" class="flex items-center justify-between gap-2 rounded-md bg-stone-50 px-3 py-2 text-sm">
                        <a class="link truncate" href="{{ route('quotes.file', $file) }}"><x-icon name="download" class="inline size-4" /> {{ $file->name }}</a>
                        <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="removeFile({{ $file->id }})" wire:confirm="Remove this attachment?">Remove</button>
                    </p>
                @endforeach
                <input type="file" class="input" multiple wire:model="uploads" aria-label="Reference attachments">
                <p wire:loading wire:target="uploads" class="text-xs text-stone-500">Uploading…</p>
                @error('uploads.*') <p class="field-error">{{ $message }}</p> @enderror
                <p class="text-xs text-stone-500">PDF, Word, PowerPoint, Excel or images, up to 20 MB each.</p>
            </div>
        </section>

        <div class="flex justify-end gap-2">
            <a href="{{ route('quote-templates.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="uploads,save">Save template</button>
        </div>
    </form>
</div>
