@php $arrow = fn ($col) => $sort === $col ? ($dir === 'asc' ? '↑' : '↓') : '↕'; @endphp
<div>
    <x-page-header title="Documents" subtitle="Upload your own documents. Only you, your managers and Super Admins can open them; company documents are open to everyone.">
        <button type="button" class="btn-primary" wire:click="create"><x-icon name="plus" /> Upload document</button>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <x-field label="Document category">
            <select class="input w-52" wire:model.live="category">
                <option value="">All</option>
                @foreach (\App\Models\Document::CATEGORIES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
            </select>
        </x-field>
        <x-field label="Date added"><input class="input w-44" type="date" wire:model.live="date"></x-field>
        @if ($category || $date || $search) <button type="button" class="btn-ghost" wire:click="$set('category', ''); $set('date', ''); $set('search', '')">Clear</button> @endif
        <div class="relative ml-auto w-full sm:w-72">
            <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
            <input class="input pl-9" type="search" placeholder="Search" wire:model.live.debounce.300ms="search" aria-label="Search documents">
        </div>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr>
                    <th><button type="button" class="cursor-pointer uppercase" wire:click="sortBy('name')">Document name {{ $arrow('name') }}</button></th>
                    <th><button type="button" class="cursor-pointer uppercase" wire:click="sortBy('category')">Type {{ $arrow('category') }}</button></th>
                    <th>Belongs to</th>
                    <th>Added by</th>
                    <th><button type="button" class="cursor-pointer uppercase" wire:click="sortBy('date')">Date added {{ $arrow('date') }}</button></th>
                    <th class="text-right">Actions</th>
                </tr></thead>
                <tbody>
                    @forelse ($documents as $doc)
                        <tr wire:key="doc-{{ $doc->id }}">
                            <td>
                                <a href="{{ route('documents.download', $doc) }}" class="font-medium text-stone-900 hover:underline">{{ $doc->name }}</a>
                                <p class="text-xs text-stone-500">{{ $doc->extension() }} · {{ $doc->sizeLabel() }}</p>
                            </td>
                            <td>{{ \App\Models\Document::CATEGORIES[$doc->category] ?? $doc->category }}</td>
                            <td>@if ($doc->shared) <span class="badge-blue">Everyone</span> @else {{ $doc->user_id === auth()->id() ? 'You' : ($doc->user?->name ?? '—') }} @endif</td>
                            <td>{{ $doc->added_by === auth()->id() ? 'You' : ($doc->addedBy?->name ?? '—') }}</td>
                            <td class="whitespace-nowrap">{{ fmt_date($doc->created_at) }}</td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('documents.download', $doc) }}" class="btn-ghost btn-sm"><x-icon name="download" /> Download</a>
                                @if ($doc->canBeDeletedBy(auth()->user()))
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $doc->id }})" wire:confirm="Delete “{{ $doc->name }}”? The file is removed and this cannot be undone.">Delete</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="!py-14 text-center">
                            <p class="text-sm font-semibold text-stone-900">No documents found</p>
                            <p class="mt-1 text-sm text-stone-500">Try adjusting your filters or upload a new document.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4 flex items-center justify-between gap-3 text-xs text-stone-500">
        <span>Showing {{ $documents->count() }} of {{ $documents->total() }} {{ $documents->total() === 1 ? 'result' : 'results' }}</span>
        <div>{{ $documents->links() }}</div>
    </div>

    @if ($showForm)
        <x-modal title="Upload document" size="max-w-xl">
            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <x-field label="Document name" name="form.name" required class="sm:col-span-2"><input class="input" type="text" wire:model="form.name" placeholder="e.g. Aadhaar card, Degree certificate"></x-field>
                <x-field label="Category" name="form.category" required>
                    <select class="input" wire:model="form.category">
                        <option value="">Select…</option>
                        @foreach (\App\Models\Document::CATEGORIES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                    </select>
                </x-field>
                @if ($people->isNotEmpty())
                    <x-field label="Belongs to" name="form.user_id" required>
                        <select class="input" wire:model="form.user_id">
                            <option value="{{ auth()->id() }}">Me</option>
                            @foreach ($people as $p) <option value="{{ $p->id }}">{{ $p->name }}</option> @endforeach
                        </select>
                    </x-field>
                @endif
                <x-field label="File" name="file" required hint="PDF, image, Word, Excel, CSV or text. Up to 10 MB." class="sm:col-span-2">
                    <input type="file" class="input" wire:model="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt">
                    <p wire:loading wire:target="file" class="mt-1 text-xs text-stone-500">Uploading…</p>
                </x-field>
                @if ($canShare)
                    <label class="flex items-start gap-2 text-sm text-stone-700 sm:col-span-2">
                        <input type="checkbox" class="mt-0.5" wire:model="form.shared">
                        <span>Company document: everyone in the team can open it. <span class="text-stone-500">Leave unticked for a personal document.</span></span>
                    </label>
                @endif
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" class="btn-secondary" wire:click="closeModal">Cancel</button>
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="file,save">Upload</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
