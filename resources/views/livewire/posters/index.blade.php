<div>
    <x-page-header title="Social Media Posters" :subtitle="$posters->total().' '.str('poster')->plural($posters->total())">
        @can('posters.manage') <button type="button" class="btn-primary" wire:click="create"><x-icon name="plus" /> Upload Poster</button> @endcan
    </x-page-header>

    <div class="card mb-5 grid gap-3 p-4 md:grid-cols-4">
        <div class="relative md:col-span-2">
            <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
            <input type="search" class="input pl-9" placeholder="Search title, caption, tags, campaign…" wire:model.live.debounce.300ms="search" aria-label="Search posters">
        </div>
        <select class="input" wire:model.live="platform" aria-label="Platform">
            <option value="">All platforms</option>
            @foreach (\App\Models\Poster::PLATFORMS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
        </select>
        <select class="input" wire:model.live="client" aria-label="Client">
            <option value="">All clients</option>
            @foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
        </select>
    </div>

    @if ($posters->isEmpty())
        <div class="card"><x-empty>No posters match. @can('posters.manage') Upload the first one to start the library. @endcan</x-empty></div>
    @else
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
            @foreach ($posters as $poster)
                <button type="button" wire:key="poster-{{ $poster->id }}" wire:click="preview({{ $poster->id }})"
                        class="card group cursor-pointer overflow-hidden text-left hover:border-stone-400" aria-label="Preview {{ $poster->title }}">
                    <div class="aspect-square bg-stone-100">
                        <img src="{{ route('posters.thumb', $poster) }}" alt="{{ $poster->title }}" loading="lazy" class="size-full object-cover transition-transform group-hover:scale-[1.03]">
                    </div>
                    <div class="p-2.5">
                        <p class="truncate text-sm font-medium text-stone-900">{{ $poster->title }}</p>
                        <p class="truncate text-xs text-stone-500">{{ \App\Models\Poster::PLATFORMS[$poster->platform] ?? $poster->platform }} · {{ $poster->client?->name ?? ($poster->campaign ?: 'Agency') }}</p>
                        <p class="text-xs text-stone-400">{{ fmt_date($poster->created_at->timezone(crm_tz())) }}</p>
                    </div>
                </button>
            @endforeach
        </div>
        @if ($posters->hasPages()) <div class="mt-5">{{ $posters->links() }}</div> @endif
    @endif

    @if ($previewing)
        <x-modal :title="$previewing->title" size="max-w-5xl">
            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <div class="flex items-center justify-center rounded-md bg-stone-100 p-2">
                    <img src="{{ route('posters.image', $previewing) }}" alt="{{ $previewing->title }}" class="max-h-[72vh] w-auto max-w-full rounded">
                </div>
                <div class="space-y-3 text-sm">
                    <dl class="space-y-2">
                        <div><dt class="label">Platform</dt><dd>{{ \App\Models\Poster::PLATFORMS[$previewing->platform] ?? $previewing->platform }}</dd></div>
                        <div><dt class="label">Client</dt><dd>{{ $previewing->client?->name ?? '—' }}</dd></div>
                        <div><dt class="label">Campaign</dt><dd>{{ $previewing->campaign ?: '—' }}</dd></div>
                        <div><dt class="label">Uploaded</dt><dd>{{ fmt_datetime($previewing->created_at) }}{{ $previewing->uploader ? ' · '.$previewing->uploader->name : '' }}</dd></div>
                        <div><dt class="label">File</dt><dd class="break-all">{{ $previewing->original_name }} · {{ \Illuminate\Support\Number::fileSize($previewing->size) }}</dd></div>
                    </dl>
                    @if ($previewing->caption) <div><p class="label">Caption</p><p class="whitespace-pre-line text-stone-700">{{ $previewing->caption }}</p></div> @endif
                    @if ($previewing->tagList()) <div class="flex flex-wrap gap-1">@foreach ($previewing->tagList() as $tag) <span class="badge-gray">#{{ $tag }}</span> @endforeach</div> @endif
                    <a href="{{ route('posters.download', $previewing) }}" class="btn-primary w-full"><x-icon name="download" /> Download original</a>
                    @can('posters.manage')
                        <div class="flex gap-2">
                            <button type="button" class="btn-secondary flex-1" wire:click="edit({{ $previewing->id }})">Edit details</button>
                            <button type="button" class="btn-danger" wire:click="delete({{ $previewing->id }})" wire:confirm="Delete this poster permanently?">Delete</button>
                        </div>
                    @endcan
                </div>
            </div>
        </x-modal>
    @endif

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit poster' : 'Upload poster'">
            <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                <x-field :label="$editingId ? 'Replace image (optional)' : 'Image'" name="image" :required="! $editingId" hint="JPG, PNG, WebP or GIF, up to 10 MB." class="md:col-span-2">
                    <input type="file" class="input" accept="image/png,image/jpeg,image/webp,image/gif" wire:model="image">
                    <p wire:loading wire:target="image" class="mt-1 text-xs text-stone-500">Uploading…</p>
                </x-field>
                <x-field label="Title" name="form.title" required><input class="input" type="text" wire:model="form.title"></x-field>
                <x-field label="Platform" name="form.platform" required>
                    <select class="input" wire:model="form.platform">
                        @foreach (\App\Models\Poster::PLATFORMS as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Client (optional)" name="form.client_id">
                    <select class="input" wire:model="form.client_id">
                        <option value="">No client</option>
                        @foreach ($clients as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
                    </select>
                </x-field>
                <x-field label="Campaign (optional)" name="form.campaign"><input class="input" type="text" wire:model="form.campaign"></x-field>
                <x-field label="Caption" name="form.caption" class="md:col-span-2"><textarea class="input" rows="3" wire:model="form.caption"></textarea></x-field>
                <x-field label="Tags" name="form.tags" hint="Comma separated, e.g. diwali, offer, reel-cover" class="md:col-span-2"><input class="input" type="text" wire:model="form.tags"></x-field>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="image,save">{{ $editingId ? 'Save changes' : 'Upload poster' }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
