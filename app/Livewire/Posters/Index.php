<?php

namespace App\Livewire\Posters;

use App\Models\Client;
use App\Models\Poster;
use App\Support\ImageThumb;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Title('Social Media Posters')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $platform = '';

    #[Url(except: '')]
    public string $client = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public $image;

    public ?int $previewId = null;

    public function boot(): void
    {
        Gate::authorize('posters.view');
    }

    public function mount(): void
    {
        $this->resetForm();
        if (request()->boolean('upload') && Gate::allows('posters.manage')) {
            $this->showForm = true;
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'platform', 'client'], true)) {
            $this->resetPage();
        }
    }

    protected function resetForm(): void
    {
        $this->form = ['title' => '', 'platform' => 'instagram', 'client_id' => '', 'campaign' => '', 'caption' => '', 'tags' => ''];
        $this->editingId = null;
        $this->image = null;
    }

    public function create(): void
    {
        Gate::authorize('posters.manage');
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('posters.manage');
        $poster = Poster::findOrFail($id);
        $this->resetForm();
        $this->resetValidation();
        $this->previewId = null;
        $this->editingId = $poster->id;
        $this->form = [
            'title' => $poster->title, 'platform' => $poster->platform, 'client_id' => (string) ($poster->client_id ?? ''),
            'campaign' => (string) $poster->campaign, 'caption' => (string) $poster->caption, 'tags' => (string) $poster->tags,
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('posters.manage');

        $this->validate([
            'form.title' => ['required', 'string', 'max:255'],
            'form.platform' => ['required', Rule::in(array_keys(Poster::PLATFORMS))],
            'form.client_id' => ['nullable', 'exists:clients,id'],
            'form.campaign' => ['nullable', 'string', 'max:255'],
            'form.caption' => ['nullable', 'string', 'max:5000'],
            'form.tags' => ['nullable', 'string', 'max:500'],
            // Raster images only: SVG is rejected because it can carry scripts.
            'image' => [$this->editingId ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240', 'dimensions:max_width=8000,max_height=8000'],
        ], [], ['form.title' => 'title', 'form.platform' => 'platform', 'form.client_id' => 'client']);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['tags'] = $data['tags'] ? collect(explode(',', $data['tags']))->map(fn ($t) => trim($t, " #\t"))->filter()->unique()->implode(', ') : null;

        $poster = $this->editingId ? Poster::findOrFail($this->editingId) : new Poster(['uploaded_by' => auth()->id()]);

        if ($this->image) {
            Storage::disk('local')->delete(array_filter([$poster->image_path, $poster->thumb_path]));
            $path = $this->image->store('posters', 'local');
            $thumb = ImageThumb::make(Storage::disk('local')->path($path));
            $thumbPath = null;
            if ($thumb) {
                $thumbPath = 'posters/thumbs/'.pathinfo($path, PATHINFO_FILENAME).'.jpg';
                Storage::disk('local')->put($thumbPath, $thumb);
            }
            $data += [
                'image_path' => $path, 'thumb_path' => $thumbPath,
                'original_name' => $this->image->getClientOriginalName(),
                'mime' => $this->image->getMimeType(), 'size' => $this->image->getSize(),
            ];
        }

        $poster->fill($data)->save();

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Poster saved.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('posters.manage');
        $poster = Poster::findOrFail($id);
        Storage::disk('local')->delete(array_filter([$poster->image_path, $poster->thumb_path]));
        $poster->delete();
        $this->previewId = null;
        $this->dispatch('toast', message: 'Poster deleted.');
    }

    public function preview(int $id): void
    {
        $this->previewId = $id;
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->previewId = null;
    }

    public function render()
    {
        $posters = Poster::query()->with('client')
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('title', 'like', "%{$s}%")
                ->orWhere('caption', 'like', "%{$s}%")->orWhere('tags', 'like', "%{$s}%")->orWhere('campaign', 'like', "%{$s}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$s}%"))))
            ->when($this->platform, fn ($q, $v) => $q->where('platform', $v))
            ->when($this->client, fn ($q, $v) => $q->where('client_id', $v))
            ->latest()->latest('id')->paginate(24);

        return view('livewire.posters.index', [
            'posters' => $posters,
            'previewing' => $this->previewId ? Poster::with(['client', 'uploader'])->find($this->previewId) : null,
            'clients' => Client::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
