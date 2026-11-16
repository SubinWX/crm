<?php

namespace App\Livewire\Office;

use App\Models\Announcement;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Office → Announcements: notices from the company to everyone. Super Admins write them; current ones also show on the dashboard. */
#[Title('Announcements')]
class Announcements extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function edit(?int $id = null): void
    {
        Gate::authorize('admin');
        $this->resetValidation();
        $item = $id ? Announcement::findOrFail($id) : null;
        $this->editingId = $item?->id;
        $this->form = ['title' => $item->title ?? '', 'body' => $item->body ?? '', 'pinned' => $item->pinned ?? false, 'expires_on' => $item?->expires_on?->format('Y-m-d') ?? ''];
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('admin');
        $this->validate([
            'form.title' => ['required', 'string', 'max:255'],
            'form.body' => ['nullable', 'string', 'max:30000'],
            'form.pinned' => ['boolean'],
            'form.expires_on' => ['nullable', 'date'],
        ], [], ['form.title' => 'title', 'form.body' => 'text', 'form.expires_on' => 'last day']);

        $data = ['title' => trim($this->form['title']), 'body' => $this->form['body'] ?: null, 'pinned' => (bool) $this->form['pinned'], 'expires_on' => $this->form['expires_on'] ?: null];
        $this->editingId ? Announcement::findOrFail($this->editingId)->update($data) : Announcement::create($data + ['created_by' => auth()->id()]);

        $this->closeModal();
        $this->dispatch('toast', message: 'Announcement saved.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('admin');
        Announcement::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Announcement deleted.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function render()
    {
        return view('livewire.office.announcements', [
            'announcements' => Announcement::with('author')->orderByDesc('pinned')->latest('id')->paginate(15),
            'canManage' => Gate::allows('admin'),
            'today' => today_local()->toDateString(),
        ]);
    }
}
