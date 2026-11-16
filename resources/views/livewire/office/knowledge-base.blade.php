<div>
    <x-page-header title="Knowledge Base" subtitle="How-to articles and process documents for the team. Anyone can add one.">
        <button type="button" class="btn-primary" wire:click="edit"><x-icon name="plus" /> New article</button>
    </x-page-header>

    @if ($open && ! $showForm)
        <article class="card">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-stone-200 px-4 py-3 sm:px-5">
                <button type="button" class="btn-ghost btn-sm" wire:click="open(null)">← All articles</button>
                <span class="flex-1"></span>
                @if ($open->canBeEditedBy(auth()->user()))
                    <button type="button" class="btn-secondary btn-sm" wire:click="edit({{ $open->id }})">Edit</button>
                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $open->id }})" wire:confirm="Delete this article? This cannot be undone.">Delete</button>
                @endif
            </div>
            <div class="px-4 py-5 sm:px-6">
                @if ($open->category) <p class="text-xs font-semibold tracking-wide text-brand-700 uppercase">{{ $open->category }}</p> @endif
                <h2 class="mt-1 text-xl font-semibold text-stone-900">{{ $open->title }}</h2>
                <p class="mt-1 text-xs text-stone-500">Written by {{ $open->author?->name ?? 'someone' }} · updated {{ fmt_date($open->updated_at) }}@if ($open->editor && $open->editor->id !== $open->created_by) by {{ $open->editor->name }}@endif</p>
                <div class="rich mt-4 max-w-3xl text-sm text-stone-800">{!! \App\Support\RichText::render($open->body, autolink: true) !!}</div>
            </div>
        </article>
    @else
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <select class="input w-auto" wire:model.live="category" aria-label="Category">
                <option value="">All categories</option>
                @foreach ($categories as $c) <option value="{{ $c }}">{{ $c }}</option> @endforeach
            </select>
            <div class="relative ml-auto w-full sm:w-80">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
                <input class="input pl-9" type="search" placeholder="Search articles" wire:model.live.debounce.300ms="search" aria-label="Search articles">
            </div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @forelse ($articles as $a)
                <button type="button" wire:key="kb-{{ $a->id }}" wire:click="open({{ $a->id }})" class="card cursor-pointer p-4 text-left transition hover:border-brand-300 hover:shadow">
                    @if ($a->category) <p class="text-[11px] font-semibold tracking-wide text-brand-700 uppercase">{{ $a->category }}</p> @endif
                    <p class="mt-0.5 text-sm font-semibold text-stone-900">{{ $a->title }}</p>
                    <p class="mt-1 line-clamp-2 text-sm text-stone-600">{{ \Illuminate\Support\Str::limit(\App\Support\RichText::plain($a->body), 140) }}</p>
                    <p class="mt-2 text-xs text-stone-500">{{ $a->author?->name ?? 'Someone' }} · {{ fmt_date($a->updated_at) }}</p>
                </button>
            @empty
                <div class="card p-8 text-center text-sm text-stone-500 sm:col-span-2 xl:col-span-3">{{ $search || $category ? 'No articles match.' : 'No articles yet. Press New article to write the first one, for example “How we onboard a new client”.' }}</div>
            @endforelse
        </div>
    @endif

    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit article' : 'New article'" size="max-w-4xl">
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-[1fr_16rem]">
                    <x-field label="Title" name="form.title" required><input class="input" type="text" wire:model="form.title" placeholder="e.g. How we onboard a new client"></x-field>
                    <x-field label="Category" name="form.category" hint="Type a new one or pick an existing one.">
                        <input class="input" type="text" wire:model="form.category" list="kb-categories" placeholder="e.g. Process, Design, HR">
                        <datalist id="kb-categories">@foreach ($categories as $c) <option value="{{ $c }}"> @endforeach</datalist>
                    </x-field>
                </div>
                <x-field label="Text" name="form.body" required><x-editor model="form.body" :value="$form['body'] ?? ''" min-height="18rem" label="Article text" /></x-field>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled">Save article</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
