<?php

namespace App\Livewire\Office;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Office → Documents. Everyone uploads their own papers (ID proofs, certificates, letters); only they,
 * their managers and Super Admins can open them. A document marked "company document" is open to everyone.
 */
#[Title('Documents')]
class Documents extends Component
{
    use WithFileUploads, WithPagination;

    protected const SORTS = ['name' => 'documents.name', 'category' => 'documents.category', 'date' => 'documents.created_at'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $date = '';

    #[Url(except: 'date')]
    public string $sort = 'date';

    #[Url(except: 'desc')]
    public string $dir = 'desc';

    public bool $showForm = false;

    public array $form = [];

    public $file;

    public function updated($property): void
    {
        if (in_array($property, ['search', 'category', 'date'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if (! isset(self::SORTS[$column])) {
            return;
        }
        $this->dir = $this->sort === $column && $this->dir === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->resetPage();
    }

    /** People the signed-in person may file a document for, besides themselves. */
    protected function people()
    {
        return auth()->user()->managedPeople()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('file');
        $this->form = ['name' => '', 'category' => '', 'user_id' => (string) auth()->id(), 'shared' => false];
        $this->showForm = true;
    }

    public function save(): void
    {
        $me = auth()->user();
        $allowed = $this->people()->pluck('id')->push($me->id)->map(fn ($id) => (string) $id)->all();
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.category' => ['required', Rule::in(array_keys(Document::CATEGORIES))],
            'form.user_id' => ['required', Rule::in($allowed)],
            'form.shared' => ['boolean'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt'],
        ], ['file.mimes' => 'Upload a PDF, image, Word, Excel, CSV or text file.', 'file.max' => 'The file may be up to 10 MB.'],
            ['form.name' => 'document name', 'form.category' => 'category', 'form.user_id' => 'person']);

        Document::create([
            'user_id' => (int) $this->form['user_id'], 'added_by' => $me->id, 'name' => trim($this->form['name']), 'category' => $this->form['category'],
            'path' => $this->file->store('documents', 'local'), 'original_name' => $this->file->getClientOriginalName(),
            'mime' => $this->file->getMimeType(), 'size' => $this->file->getSize(),
            // Only Super Admins publish a document to the whole company.
            'shared' => $me->isSuperAdmin() && (bool) $this->form['shared'],
        ]);

        $this->closeModal();
        $this->dispatch('toast', message: 'Document uploaded.');
    }

    public function delete(int $id): void
    {
        $document = Document::findOrFail($id);
        abort_unless($document->canBeDeletedBy(auth()->user()), 403);
        Storage::disk('local')->delete($document->path);
        $document->delete();
        $this->dispatch('toast', message: 'Document deleted.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->reset('file');
    }

    public function render()
    {
        $me = auth()->user();
        $sort = self::SORTS[$this->sort] ?? self::SORTS['date'];

        $documents = Document::query()->visibleTo($me)->with(['user', 'addedBy'])
            ->when(isset(Document::CATEGORIES[$this->category]), fn ($q) => $q->where('category', $this->category))
            ->when(preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date), fn ($q) => $q->whereDate('created_at', $this->date))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('original_name', 'like', "%{$s}%")
                ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$s}%"))))
            ->orderBy($sort, $this->dir === 'asc' ? 'asc' : 'desc')->orderByDesc('documents.id')->paginate(25);

        return view('livewire.office.documents', [
            'documents' => $documents,
            'people' => $this->showForm ? $this->people() : collect(),
            'canShare' => $me->isSuperAdmin(),
        ]);
    }
}
