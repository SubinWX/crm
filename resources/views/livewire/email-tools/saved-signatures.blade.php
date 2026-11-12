<div>
    <x-page-header title="Saved Signatures" :subtitle="auth()->user()->isSuperAdmin() ? 'Your saved signatures, and those your team has saved.' : 'The email signatures you have saved.'">
        <a href="{{ route('email-tools.signature', ['new' => 1]) }}" class="btn-primary"><x-icon name="plus" /> New signature</a>
    </x-page-header>

    <div class="card">
        <div class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
            <div class="relative w-full md:w-80">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 size-4 text-stone-400" />
                <input type="search" class="input pl-9" placeholder="Search by name or designation…" wire:model.live.debounce.300ms="search" aria-label="Search saved signatures">
            </div>
            <p class="ml-auto text-sm text-stone-500">{{ $signatures->count() }} saved</p>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Signature</th><th>For</th><th>Layout</th><th>Saved by</th><th>Last saved</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($signatures as $s)
                        <tr wire:key="signature-{{ $s->id }}">
                            <td class="max-w-64"><a href="{{ route('email-tools.signature', ['open' => $s->id]) }}" class="font-medium text-stone-900 hover:underline">{{ $s->name }}</a></td>
                            <td class="max-w-64"><p class="truncate">{{ $s->data['name'] ?? '—' }}</p><p class="truncate text-xs text-stone-500">{{ $s->data['title'] ?? '' }}</p></td>
                            <td>{{ str(\App\Support\EmailBuilder::SIGNATURE_LAYOUTS[$s->data['layout'] ?? 'classic'] ?? 'Classic')->before(':') }}</td>
                            <td>{{ $s->user_id === auth()->id() ? 'You' : ($s->user?->name ?? '—') }}</td>
                            <td class="whitespace-nowrap">{{ fmt_datetime($s->updated_at) }}</td>
                            <td>@include('livewire.email-tools._saved-row-actions', ['id' => $s->id, 'name' => $s->name, 'code' => $html[$s->id], 'editUrl' => route('email-tools.signature', ['open' => $s->id]), 'canDelete' => true, 'rich' => 'Copy signature'])</td>
                        </tr>
                    @empty
                        <x-empty colspan="6">{{ $search !== '' ? 'No saved signature matches.' : 'No signatures saved yet. Build one in Email Signatures, name it and press Save.' }}</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($previewing)
        <x-modal :title="$previewing->name" size="max-w-3xl">
            <iframe title="Signature preview" sandbox="allow-same-origin" class="block h-80 w-full rounded-md border border-stone-200 bg-white"
                    srcdoc="{{ '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:18px;background:#ffffff;">'.\App\Support\EmailBuilder::signature($previewing->data ?? []).'</body></html>' }}"></iframe>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" class="btn-secondary" wire:click="closeModal">Close</button>
                <a href="{{ route('email-tools.signature', ['open' => $previewing->id]) }}" class="btn-primary">Open to edit</a>
            </div>
        </x-modal>
    @endif
</div>
