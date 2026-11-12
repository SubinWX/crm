<div>
    <x-page-header title="Saved Email Templates" subtitle="Every saved template, shared with your team.">
        <a href="{{ route('email-tools.templates') }}" class="btn-primary"><x-icon name="plus" /> New template</a>
    </x-page-header>

    <div class="card">
        <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
            <div class="relative w-full md:w-80">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
                <input type="search" class="input pl-9" placeholder="Search by name or heading…" wire:model.live.debounce.300ms="search" aria-label="Search saved templates">
            </div>
            <p class="ml-auto text-sm text-stone-500">{{ $templates->count() }} saved</p>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Template</th><th>Heading</th><th>Layout</th><th>Saved by</th><th>Last saved</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($templates as $t)
                        <tr wire:key="template-{{ $t->id }}">
                            <td class="max-w-64"><a href="{{ route('email-tools.templates', ['open' => $t->id]) }}" class="font-medium text-stone-900 hover:underline">{{ $t->name }}</a></td>
                            <td class="max-w-72 truncate">{{ $t->data['heading'] ?? '—' }}</td>
                            <td>{{ str(\App\Support\EmailBuilder::TEMPLATE_LAYOUTS[$t->data['layout'] ?? 'band'] ?? 'Band')->before(':') }}</td>
                            <td>{{ (int) $t->created_by === auth()->id() ? 'You' : ($t->creator?->name ?? '—') }}</td>
                            <td class="whitespace-nowrap">{{ fmt_datetime($t->updated_at) }}</td>
                            <td>@include('livewire.email-tools._saved-row-actions', ['id' => $t->id, 'name' => $t->name, 'code' => $html[$t->id], 'editUrl' => route('email-tools.templates', ['open' => $t->id]), 'canDelete' => auth()->user()->isSuperAdmin() || (int) $t->created_by === auth()->id(), 'rich' => 'Copy email'])</td>
                        </tr>
                    @empty
                        <x-empty colspan="6">{{ $search !== '' ? 'No saved template matches.' : 'No templates saved yet. Build one in Email Templates, name it and press Save.' }}</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($previewing)
        <x-modal :title="$previewing->name" size="max-w-3xl">
            <iframe title="Template preview" sandbox="allow-same-origin" class="block h-[32rem] w-full rounded-md border border-stone-200 bg-white" srcdoc="{{ \App\Support\EmailBuilder::template($previewing->data ?? []) }}"></iframe>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="btn-secondary" wire:click="closeModal">Close</button>
                <a href="{{ route('email-tools.templates', ['open' => $previewing->id]) }}" class="btn-primary">Open to edit</a>
            </div>
        </x-modal>
    @endif
</div>
