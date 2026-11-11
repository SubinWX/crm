<?php

namespace App\Livewire\Data;

use App\Support\CsvImporter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Three steps: upload a CSV, map its columns, review the preview and duplicate checks, import. */
#[Title('Import CSV')]
class Import extends Component
{
    use WithFileUploads;

    /** leads | clients */
    public string $type = 'leads';

    public $file;

    /** Stored copy of the uploaded CSV on the private disk. */
    #[Locked]
    public ?string $path = null;

    public int $step = 1;

    public array $headers = [];

    /** field => CSV column index */
    public array $mapping = [];

    public bool $includeDuplicates = false;

    public ?array $result = null;

    public function mount(): void
    {
        $this->type = request()->query('type') === 'clients' ? 'clients' : 'leads';
        $this->authorizeType();
    }

    public function boot(): void
    {
        Gate::authorize('workspace');
    }

    /** Leads need sales.manage; clients need clients.manage. */
    protected function authorizeType(): void
    {
        abort_unless(array_key_exists($this->type, CsvImporter::FIELDS), 422);
        Gate::authorize($this->type === 'leads' ? 'sales.manage' : 'clients.manage');
    }

    public function updatedType(): void
    {
        $this->authorizeType();
    }

    public function upload(): void
    {
        $this->authorizeType();
        $this->validate(['file' => ['required', 'file', 'max:5120', 'mimes:csv,txt']], [], ['file' => 'CSV file']);

        $this->path = $this->file->store('imports', 'local');
        $csv = CsvImporter::read(Storage::disk('local')->path($this->path));
        if (! $csv['headers'] || ! $csv['rows']) {
            Storage::disk('local')->delete($this->path);
            $this->path = null;
            $this->addError('file', 'That file has no rows to import. The first row must be the column headings.');

            return;
        }

        $this->headers = $csv['headers'];
        $this->mapping = CsvImporter::guessMapping($this->type, $this->headers);
        $this->step = 2;
        $this->reset('file');
    }

    public function preview(): void
    {
        $this->authorizeType();
        if (($this->mapping['name'] ?? '') === '') {
            $this->addError('mapping.name', 'Choose the column that holds the name.');

            return;
        }
        $this->step = 3;
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    protected function analysed(): array
    {
        if (! $this->path || ! Storage::disk('local')->exists($this->path)) {
            return [];
        }

        return CsvImporter::analyse($this->type, CsvImporter::read(Storage::disk('local')->path($this->path))['rows'], $this->mapping);
    }

    public function import(): void
    {
        $this->authorizeType();
        $analysed = $this->analysed();
        abort_if($analysed === [], 422);

        $this->result = CsvImporter::import($this->type, $analysed, $this->includeDuplicates, auth()->id());
        activity('imported', "{$this->result['imported']} {$this->type} imported from CSV ({$this->result['skipped']} skipped)");

        Storage::disk('local')->delete($this->path);
        $this->path = null;
        $this->step = 4;
    }

    public function startOver(): void
    {
        if ($this->path) {
            Storage::disk('local')->delete($this->path);
        }
        $this->reset(['file', 'path', 'headers', 'mapping', 'includeDuplicates', 'result']);
        $this->step = 1;
    }

    public function render()
    {
        $analysed = $this->step === 3 ? $this->analysed() : [];
        $counts = collect($analysed)->countBy('status');

        return view('livewire.data.import', [
            'fields' => CsvImporter::FIELDS[$this->type],
            'sample' => array_slice($analysed, 0, 25),
            'counts' => ['new' => $counts['new'] ?? 0, 'duplicate' => $counts['duplicate'] ?? 0, 'invalid' => $counts['invalid'] ?? 0, 'total' => count($analysed)],
            'canLeads' => Gate::allows('sales.manage'),
            'canClients' => Gate::allows('clients.manage'),
        ]);
    }
}
