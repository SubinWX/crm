<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\ClientFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Files extends Component
{
    use WithFileUploads;

    public Client $client;

    public $upload;

    public string $category = 'contract';

    public function booted(): void
    {
        Gate::authorize('client.access', $this->client);
    }

    public function save(): void
    {
        Gate::authorize('client.update', $this->client);
        $this->validate([
            'upload' => ['required', 'file', 'max:15360', 'mimes:pdf,doc,docx,xls,xlsx,csv,txt,png,jpg,jpeg,webp,zip'],
            'category' => ['required', Rule::in(array_keys(ClientFile::CATEGORIES))],
        ], [], ['upload' => 'file']);

        $path = $this->upload->store("clients/{$this->client->id}", 'local');

        $this->client->files()->create([
            'category' => $this->category,
            'name' => $this->upload->getClientOriginalName(),
            'path' => $path,
            'mime' => $this->upload->getMimeType(),
            'size' => $this->upload->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        $this->reset('upload');
        $this->dispatch('toast', message: 'File uploaded.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('clients.manage');
        $file = $this->client->files()->findOrFail($id);
        Storage::disk('local')->delete($file->path);
        $file->delete();
        $this->dispatch('toast', message: 'File deleted.');
    }

    public function render()
    {
        return view('livewire.clients.files', [
            'files' => $this->client->files()->with('uploader')->latest()->get(),
        ]);
    }
}
