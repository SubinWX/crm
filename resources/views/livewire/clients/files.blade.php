<div class="space-y-5">
    @can('client.update', $client)
        <form wire:submit="save" class="card flex flex-wrap items-end gap-4 p-4">
            <x-field label="File" name="upload" hint="PDF, Office documents, images or ZIP. Up to 15 MB." class="min-w-64 flex-1">
                <input type="file" class="input" wire:model="upload">
            </x-field>
            <x-field label="Type" name="category">
                <select class="input" wire:model="category">
                    @foreach (\App\Models\ClientFile::CATEGORIES as $key => $label) <option value="{{ $key }}">{{ $label }}</option> @endforeach
                </select>
            </x-field>
            <button type="submit" class="btn-primary mb-0.5" wire:loading.attr="disabled" wire:target="upload,save">
                <span wire:loading.remove wire:target="upload">Upload</span><span wire:loading wire:target="upload">Uploading…</span>
            </button>
        </form>
    @endcan

    <section class="card">
        <div class="card-header"><h2 class="card-title">Files &amp; contract attachments</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Type</th><th>Size</th><th>Uploaded</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($files as $file)
                        <tr wire:key="file-{{ $file->id }}">
                            <td class="max-w-80 truncate font-medium text-stone-900">{{ $file->name }}</td>
                            <td><span class="badge-gray">{{ \App\Models\ClientFile::CATEGORIES[$file->category] ?? $file->category }}</span></td>
                            <td class="whitespace-nowrap">{{ \Illuminate\Support\Number::fileSize($file->size) }}</td>
                            <td class="whitespace-nowrap">{{ fmt_datetime($file->created_at) }}<span class="text-stone-500"> · {{ $file->uploader?->name ?? '—' }}</span></td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('files.client', $file) }}" class="btn-ghost btn-sm"><x-icon name="download" /> Download</a>
                                @can('clients.manage')
                                    <button type="button" class="btn-ghost btn-sm text-red-700" wire:click="delete({{ $file->id }})" wire:confirm="Delete {{ $file->name }}? This cannot be undone.">Delete</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="5">No files uploaded for this client.</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
