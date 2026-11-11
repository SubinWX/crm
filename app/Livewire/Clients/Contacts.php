<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Contacts extends Component
{
    public Client $client;

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function booted(): void
    {
        Gate::authorize('client.access', $this->client);
    }

    public function mount(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->form = ['name' => '', 'role' => 'other', 'email' => '', 'phone' => '', 'is_primary' => false, 'notes' => ''];
        $this->editingId = null;
    }

    public function create(): void
    {
        Gate::authorize('client.update', $this->client);
        $this->resetForm();
        $this->form['is_primary'] = $this->client->contacts()->doesntExist();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('client.update', $this->client);
        $contact = $this->client->contacts()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $contact->id;
        $this->form = ['name' => $contact->name, 'role' => $contact->role, 'email' => (string) $contact->email, 'phone' => (string) $contact->phone, 'is_primary' => $contact->is_primary, 'notes' => (string) $contact->notes];
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('client.update', $this->client);
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.role' => ['required', Rule::in(array_keys(ClientContact::ROLES))],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.is_primary' => ['boolean'],
            'form.notes' => ['nullable', 'string', 'max:500'],
        ], [], ['form.name' => 'name', 'form.email' => 'email', 'form.phone' => 'phone']);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['is_primary'] = (bool) $this->form['is_primary'];

        $contact = $this->editingId ? $this->client->contacts()->findOrFail($this->editingId) : new ClientContact(['client_id' => $this->client->id]);
        $contact->fill($data)->save();
        if ($contact->is_primary) {
            $this->makePrimary($contact);
        }

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Contact saved.');
    }

    public function setPrimary(int $id): void
    {
        Gate::authorize('client.update', $this->client);
        $this->makePrimary($this->client->contacts()->findOrFail($id));
    }

    /** One primary contact per client; the client's own contact fields follow it. */
    protected function makePrimary(ClientContact $contact): void
    {
        $this->client->contacts()->where('id', '!=', $contact->id)->update(['is_primary' => false]);
        $contact->update(['is_primary' => true, 'role' => $contact->role === 'other' ? 'primary' : $contact->role]);
        $this->client->update(['contact_name' => $contact->name, 'email' => $contact->email ?: $this->client->email, 'phone1' => $contact->phone ?: $this->client->phone1]);
    }

    public function delete(int $id): void
    {
        Gate::authorize('client.update', $this->client);
        $this->client->contacts()->findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Contact removed. Quotes and invoices already addressed to them keep the name.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        return view('livewire.clients.contacts', ['contacts' => $this->client->contacts()->get()]);
    }
}
