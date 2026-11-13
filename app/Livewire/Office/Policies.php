<?php

namespace App\Livewire\Office;

use App\Models\Policy;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Office → Rules & Policies: the company's rules, read by everyone and written by Super Admins. */
#[Title('Rules & Policies')]
class Policies extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = ['title' => '', 'body' => '', 'sort_order' => '1'];

    public function edit(?int $id = null): void
    {
        Gate::authorize('admin');
        $this->resetValidation();
        $policy = $id ? Policy::findOrFail($id) : null;
        $this->editingId = $policy?->id;
        $this->form = ['title' => $policy->title ?? '', 'body' => $policy->body ?? '', 'sort_order' => (string) ($policy->sort_order ?? ((int) Policy::max('sort_order') + 1))];
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('admin');
        $this->validate([
            'form.title' => ['required', 'string', 'max:255'],
            'form.body' => ['required', 'string', 'max:60000'],
            'form.sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ], [], ['form.title' => 'title', 'form.body' => 'text', 'form.sort_order' => 'position']);

        $data = ['title' => trim($this->form['title']), 'body' => $this->form['body'], 'sort_order' => (int) $this->form['sort_order'], 'updated_by' => auth()->id()];
        $this->editingId ? Policy::findOrFail($this->editingId)->update($data) : Policy::create($data);

        $this->closeModal();
        $this->dispatch('toast', message: 'Policy saved.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('admin');
        Policy::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Policy deleted.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function render()
    {
        return view('livewire.office.policies', [
            'policies' => Policy::orderBy('sort_order')->orderBy('id')->get(),
            'canManage' => Gate::allows('admin'),
        ]);
    }
}
