<?php

namespace App\Livewire\Work;

use App\Models\Client;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Projects and ongoing services. Global page, and a tab inside a client profile (clientId set). */
#[Title('Projects & Services')]
class Projects extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $clientId = null;

    #[Url(except: '')]
    public string $search = '';

    /** open | all | one of Project::STATUSES */
    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: '')]
    public string $owner = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function mount(?int $clientId = null): void
    {
        $this->clientId = $clientId;
        $this->resetForm();
    }

    public function booted(): void
    {
        Gate::authorize('workspace');
        $this->clientId ? Gate::authorize('client.access', Client::findOrFail($this->clientId)) : Gate::authorize('clients.view');
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'owner'], true)) {
            $this->resetPage();
        }
    }

    protected function resetForm(): void
    {
        $this->form = [
            'client_id' => (string) ($this->clientId ?? ''), 'name' => '', 'kind' => 'project', 'owner_id' => (string) auth()->id(), 'status' => 'planned',
            'deadline' => '', 'next_milestone' => '', 'next_milestone_date' => '', 'quote_id' => '', 'notes' => '',
        ];
        $this->editingId = null;
    }

    protected function find(?int $id): Project
    {
        return Project::query()->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))->findOrFail($id);
    }

    public function create(): void
    {
        Gate::authorize('clients.manage');
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('clients.manage');
        $project = $this->find($id);
        $this->resetValidation();
        $this->editingId = $project->id;
        foreach (array_keys($this->form) as $key) {
            $value = $project->{$key};
            $this->form[$key] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) ($value ?? '');
        }
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('clients.manage');
        if ($this->clientId) {
            $this->form['client_id'] = (string) $this->clientId;
        }
        $this->validate([
            'form.client_id' => ['required', 'exists:clients,id'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.kind' => ['required', Rule::in(array_keys(Project::KINDS))],
            'form.owner_id' => ['nullable', 'exists:users,id'],
            'form.status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'form.deadline' => ['nullable', 'date'],
            'form.next_milestone' => ['nullable', 'string', 'max:255'],
            'form.next_milestone_date' => ['nullable', 'date'],
            // Only an accepted quote of the same client can be linked.
            'form.quote_id' => ['nullable', Rule::exists('quotes', 'id')->where('client_id', $this->form['client_id'] ?: 0)->where('status', 'accepted')],
            'form.notes' => ['nullable', 'string', 'max:5000'],
        ], ['form.quote_id.exists' => 'Choose an accepted quote that belongs to this client.'],
            collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace(['_id', '_'], ['', ' '], $k)])->all());

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $this->editingId ? $this->find($this->editingId)->update($data) : Project::create($data);

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Saved.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('clients.manage');
        $this->find($id)->delete();
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        $projects = Project::query()->with(['client', 'owner', 'quote'])
            ->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))
            ->when($this->status === 'open', fn ($q) => $q->open())
            ->when(array_key_exists($this->status, Project::STATUSES), fn ($q) => $q->where('status', $this->status))
            ->when($this->owner, fn ($q, $v) => $q->where('owner_id', $v))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('next_milestone', 'like', "%{$s}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$s}%"))))
            ->orderByRaw('deadline is null')->orderBy('deadline')->latest('id')->paginate(25);

        $formClient = $this->form['client_id'] ?? null;

        return view('livewire.work.projects', [
            'projects' => $projects,
            'users' => User::active()->where('role', '!=', User::ROLE_SUB_ADMIN)->orderBy('name')->get(['id', 'name']),
            'clients' => $this->showForm && ! $this->clientId ? Client::notArchived()->orderBy('name')->get(['id', 'name']) : collect(),
            'quotes' => $this->showForm && $formClient ? Quote::where('client_id', $formClient)->where('status', 'accepted')->latest('id')->get(['id', 'number', 'title']) : collect(),
            'canManage' => Gate::allows('clients.manage'),
        ]);
    }
}
