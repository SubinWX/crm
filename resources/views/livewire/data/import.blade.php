<div class="mx-auto max-w-5xl">
    <x-page-header title="Import CSV" subtitle="Bring in leads or clients from a spreadsheet. Nothing is saved until the last step.">
        <a href="{{ $type === 'leads' ? route('leads.index') : route('clients.index') }}" class="btn-secondary">Cancel</a>
    </x-page-header>

    <ol class="mb-5 flex flex-wrap gap-2 text-sm" aria-label="Steps">
        @foreach ([1 => 'Upload', 2 => 'Match columns', 3 => 'Preview and check', 4 => 'Done'] as $number => $label)
            <li @class(['rounded-full px-3 py-1 font-medium', 'bg-ink-900 text-white' => $step === $number, 'bg-stone-200 text-stone-600' => $step !== $number])>{{ $number }}. {{ $label }}</li>
        @endforeach
    </ol>

    @if ($step === 1)
        <form wire:submit="upload" class="card space-y-5 p-5">
            <x-field label="What are you importing?" name="type">
                <div class="flex flex-wrap gap-2">
                    @foreach (['leads' => $canLeads, 'clients' => $canClients] as $key => $allowed)
                        @if ($allowed)
                            <label class="flex cursor-pointer items-center gap-2 rounded-md border border-stone-300 bg-white px-4 py-2 text-sm has-checked:border-brand-500 has-checked:bg-brand-50"><input type="radio" name="type" value="{{ $key }}" wire:model.live="type"> {{ ucfirst($key) }}</label>
                        @endif
                    @endforeach
                </div>
            </x-field>
            <x-field label="CSV file" name="file" hint="First row must be column headings. Up to 5 MB and {{ number_format(\App\Support\CsvImporter::MAX_ROWS) }} rows. In Excel or Google Sheets use File → Download / Save as → CSV.">
                <input type="file" class="input" accept=".csv,text/csv" wire:model="file">
                <p wire:loading wire:target="file" class="mt-1 text-xs text-stone-500">Uploading…</p>
            </x-field>
            <div class="flex justify-end"><button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="file,upload">Continue</button></div>
        </form>
    @elseif ($step === 2)
        <form wire:submit="preview" class="card">
            <div class="card-header"><h2 class="card-title">Match your columns to {{ $type }} fields</h2><span class="text-xs text-stone-500">We guessed from the headings. Leave a field on “Don’t import” to skip it.</span></div>
            <div class="grid gap-4 p-5 md:grid-cols-2">
                @foreach ($fields as $field => [$label])
                    <x-field :label="$label" :name="'mapping.'.$field" :required="$field === 'name'">
                        <select class="input" wire:model="mapping.{{ $field }}">
                            <option value="">Don’t import</option>
                            @foreach ($headers as $index => $header) <option value="{{ $index }}">{{ $header ?: 'Column '.($index + 1) }}</option> @endforeach
                        </select>
                    </x-field>
                @endforeach
            </div>
            <div class="flex justify-between border-t border-stone-200 p-4">
                <button type="button" class="btn-secondary" wire:click="startOver">Choose another file</button>
                <button type="submit" class="btn-primary">Preview</button>
            </div>
        </form>
    @elseif ($step === 3)
        <div class="mb-5 grid gap-4 sm:grid-cols-4">
            <x-stat label="Rows in file" :value="$counts['total']" />
            <x-stat label="Will be imported" :value="$counts['new'] + ($includeDuplicates ? $counts['duplicate'] : 0)" />
            <x-stat label="Duplicates" :value="$counts['duplicate']" :hint="$includeDuplicates ? 'Will be imported anyway' : 'Will be skipped'" :tone="$counts['duplicate'] ? 'danger' : 'default'" />
            <x-stat label="Invalid" :value="$counts['invalid']" hint="Always skipped" :tone="$counts['invalid'] ? 'danger' : 'default'" />
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Preview</h2><span class="text-xs text-stone-500">First {{ count($sample) }} of {{ $counts['total'] }} rows</span></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Row</th><th>Result</th>@foreach ($fields as $field => [$label]) @if (($mapping[$field] ?? '') !== '') <th>{{ $label }}</th> @endif @endforeach</tr></thead>
                    <tbody>
                        @foreach ($sample as $row)
                            <tr wire:key="row-{{ $row['line'] }}">
                                <td class="tabular-nums text-stone-500">{{ $row['line'] }}</td>
                                <td class="whitespace-nowrap">
                                    @if ($row['status'] === 'new') <span class="badge-green">New</span>
                                    @elseif ($row['status'] === 'duplicate') <span class="badge-amber">Duplicate</span><div class="text-xs text-stone-500">{{ $row['reason'] }}</div>
                                    @else <span class="badge-red">Invalid</span><div class="text-xs text-stone-500">{{ $row['reason'] }}</div> @endif
                                </td>
                                @foreach ($fields as $field => [$label]) @if (($mapping[$field] ?? '') !== '') <td class="max-w-56 truncate">{{ $row['data'][$field] ?? '—' }}</td> @endif @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-200 p-4">
                <button type="button" class="btn-secondary" wire:click="back">Back to columns</button>
                <label class="flex items-center gap-2 text-sm text-stone-700"><input type="checkbox" class="rounded border-stone-300" wire:model.live="includeDuplicates"> Import duplicates as well</label>
                <button type="button" class="btn-primary" wire:click="import" wire:loading.attr="disabled" @disabled(($counts['new'] + ($includeDuplicates ? $counts['duplicate'] : 0)) === 0)
                        wire:confirm="Import {{ $counts['new'] + ($includeDuplicates ? $counts['duplicate'] : 0) }} {{ $type }} now?">Import {{ $counts['new'] + ($includeDuplicates ? $counts['duplicate'] : 0) }} {{ $type }}</button>
            </div>
        </div>
    @else
        <div class="card p-8 text-center">
            <p class="text-lg font-semibold text-stone-900">{{ $result['imported'] }} {{ $type }} imported</p>
            <p class="mt-1 text-sm text-stone-600">{{ $result['skipped'] }} rows were skipped (duplicates or invalid).</p>
            <div class="mt-5 flex justify-center gap-2">
                <button type="button" class="btn-secondary" wire:click="startOver">Import another file</button>
                <a href="{{ $type === 'leads' ? route('leads.index') : route('clients.index') }}" class="btn-primary">View {{ $type }}</a>
            </div>
        </div>
    @endif
</div>
