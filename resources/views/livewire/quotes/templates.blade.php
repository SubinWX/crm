<div>
    <div class="mb-1 text-sm"><a href="{{ route('settings.index') }}" class="link">← Settings</a></div>
    <x-page-header title="Quote Templates" subtitle="Reusable sections and price lists for each product or service. Changing a template never changes a quote that is already saved.">
        <label class="flex items-center gap-2 text-sm text-stone-600"><input type="checkbox" class="rounded border-stone-300" wire:model.live="archived"> Show archived</label>
        <a href="{{ route('quote-templates.create') }}" class="btn-primary"><x-icon name="plus" /> New template</a>
    </x-page-header>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Template</th><th>Product / service</th><th>Sections</th><th>Line items</th><th>Quote valid for</th><th>Updated</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr wire:key="tpl-{{ $template->id }}">
                            <td class="max-w-96">
                                <a class="link text-stone-900" href="{{ route('quote-templates.edit', $template) }}">{{ $template->name }}</a>
                                @if ($template->description) <p class="line-clamp-1 text-xs text-stone-500">{{ $template->description }}</p> @endif
                            </td>
                            <td>{{ $template->category ?: '—' }}</td>
                            <td class="tabular-nums">{{ $template->sections_count }}</td>
                            <td class="tabular-nums">{{ $template->items_count }}</td>
                            <td>{{ $template->validity_days }} days</td>
                            <td class="whitespace-nowrap">{{ fmt_date($template->updated_at->timezone(crm_tz())) }}</td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('quote-templates.edit', $template) }}" class="btn-ghost btn-sm">Edit</a>
                                <button type="button" class="btn-ghost btn-sm" wire:click="duplicate({{ $template->id }})">Duplicate</button>
                                <button type="button" class="btn-ghost btn-sm" wire:click="toggleArchive({{ $template->id }})">{{ $template->archived_at ? 'Restore' : 'Archive' }}</button>
                            </td>
                        </tr>
                    @empty
                        <x-empty colspan="7">{{ $archived ? 'No archived templates.' : 'No templates yet. Create one for each product or service you quote for.' }}</x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
