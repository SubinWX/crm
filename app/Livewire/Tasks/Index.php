<?php

namespace App\Livewire\Tasks;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Tasks and follow-ups in one list. Used as the Tasks page, as a tab on a client profile
 * (clientId), as a panel on a lead (leadId) and as the task list of one project (projectId).
 */
#[Title('Tasks & Follow-ups')]
class Index extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $clientId = null;

    #[Locked]
    public ?int $leadId = null;

    #[Locked]
    public ?int $projectId = null;

    #[Url(except: '')]
    public string $search = '';

    /** Project id, or 'none' for tasks outside any project. */
    #[Url(except: '')]
    public string $project = '';

    /** open | due | overdue | todo | in_progress | done | all */
    #[Url(except: 'open')]
    public string $status = 'open';

    #[Url(except: '')]
    public string $assignee = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    /** User ids of the people on the task being added or edited. */
    public array $assignees = [];

    public function mount(?int $clientId = null, ?int $leadId = null, ?int $projectId = null): void
    {
        $this->leadId = $leadId;
        $this->projectId = $projectId;
        // A project's tasks always belong to the project's client.
        $this->clientId = $projectId ? Project::findOrFail($projectId)->client_id : $clientId;
        $this->resetForm();
    }

    public function booted(): void
    {
        Gate::authorize('tasks.view');
        if ($this->leadId) {
            Gate::authorize('sales.manage'); // leads belong to sales
        }
        if ($this->projectId) {
            Gate::authorize('projects.view'); // tasks shown on a project page
        } elseif ($this->clientId) {
            Gate::authorize('client.access', Client::findOrFail($this->clientId));
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'status', 'assignee', 'project'], true)) {
            $this->resetPage();
        }
        // Changing the client clears a project that belongs to another client.
        if ($property === 'form.client_id' || $property === 'form.for') {
            $this->form['project_id'] = '';
        }
    }

    /** Creating and editing: tasks.manage, or sales staff working on a lead. */
    public function canManage(): bool
    {
        return Gate::allows('tasks.manage') || ($this->leadId !== null && Gate::allows('sales.manage'));
    }

    protected function resetForm(): void
    {
        $this->form = [
            'for' => $this->leadId ? 'lead' : 'client',
            'client_id' => (string) ($this->clientId ?? ''), 'lead_id' => (string) ($this->leadId ?? ''),
            'project_id' => (string) ($this->projectId ?? ''),
            'title' => '', 'due_date' => '', 'status' => 'todo', 'notes' => '',
        ];
        $this->assignees = [(string) auth()->id()];
        $this->editingId = null;
    }

    public function create(): void
    {
        abort_unless($this->canManage(), 403);
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $task = $this->findTask($id);
        $this->resetValidation();
        $this->editingId = $task->id;
        $this->form = [
            'for' => $task->client_id ? 'client' : 'lead',
            'client_id' => (string) ($task->client_id ?? ''), 'lead_id' => (string) ($task->lead_id ?? ''),
            'project_id' => (string) ($task->project_id ?? ''), 'title' => $task->title,
            'due_date' => $task->due_date?->format('Y-m-d') ?? '', 'status' => $task->status, 'notes' => (string) $task->notes,
        ];
        $this->assignees = $task->assignees()->pluck('users.id')->map(fn ($id) => (string) $id)->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless($this->canManage(), 403);

        // Inside a profile the owner is fixed; on the Tasks page the person chooses a client or (sales only) a lead.
        if ($this->clientId) {
            $this->form = ['for' => 'client', 'client_id' => (string) $this->clientId, 'lead_id' => ''] + $this->form;
        } elseif ($this->leadId) {
            $this->form = ['for' => 'lead', 'lead_id' => (string) $this->leadId, 'client_id' => ''] + $this->form;
        }
        if ($this->projectId) {
            $this->form['project_id'] = (string) $this->projectId;
        }
        $forLead = $this->form['for'] === 'lead';
        if ($forLead) {
            $this->form['project_id'] = '';
        }
        abort_if($forLead && ! Gate::allows('sales.manage'), 403);

        $this->validate([
            'form.for' => ['required', Rule::in(['client', 'lead'])],
            'form.client_id' => [Rule::requiredIf(! $forLead), 'nullable', 'exists:clients,id'],
            'form.lead_id' => [Rule::requiredIf($forLead), 'nullable', 'exists:leads,id'],
            'form.title' => ['required', 'string', 'max:255'],
            // Only a project of the same client can be chosen.
            'form.project_id' => ['nullable', Rule::exists('projects', 'id')->where('client_id', $this->form['client_id'] ?: 0)],
            'assignees' => ['array'],
            'assignees.*' => [Rule::exists('users', 'id')->where('is_active', true)->whereNot('role', User::ROLE_SUB_ADMIN)],
            'form.due_date' => ['nullable', 'date'],
            'form.status' => ['required', Rule::in(array_keys(Task::STATUSES))],
            'form.notes' => ['nullable', 'string', 'max:5000'],
        ], ['form.project_id.exists' => 'Choose a project that belongs to this client.'], ['form.client_id' => 'client', 'form.lead_id' => 'lead', 'form.project_id' => 'project', 'form.title' => 'title', 'assignees.*' => 'person', 'form.due_date' => 'due date']);

        $lead = $forLead ? Lead::find($this->form['lead_id']) : null;
        $data = [
            // A task on a converted lead is filed under its client too, so it shows on both.
            'client_id' => $forLead ? $lead?->client_id : $this->form['client_id'],
            'lead_id' => $forLead ? $lead?->id : null,
            'project_id' => $forLead ? null : ($this->form['project_id'] ?: null),
            'title' => $this->form['title'],
            'due_date' => $this->form['due_date'] ?: null, 'status' => $this->form['status'], 'notes' => $this->form['notes'] ?: null,
        ];

        $task = $this->editingId
            ? tap($this->findTask($this->editingId))->update($data)
            : Task::create($data + ['created_by' => auth()->id()]);
        $task->assignTo($this->assignees);

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Task saved.');
    }

    public function setStatus(int $id, string $status): void
    {
        // Assignees may move their own tasks along without the manage permission.
        $task = $this->findTask($id);
        abort_unless($this->canManage() || $task->isAssignedTo(auth()->id()), 403);
        abort_unless(array_key_exists($status, Task::STATUSES), 422);
        $task->update(['status' => $status]);
    }

    public function delete(int $id): void
    {
        abort_unless($this->canManage(), 403);
        $this->findTask($id)->delete();
        $this->dispatch('toast', message: 'Task deleted.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    protected function findTask(?int $id): Task
    {
        return Task::query()
            ->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))
            ->when($this->leadId, fn ($q) => $q->where('lead_id', $this->leadId))
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->findOrFail($id);
    }

    /**
     * Sales reminders that live on the lead or the quote itself rather than as tasks:
     * a lead's next action and a quote's follow-up date, when due. Shown above the list on the Tasks page.
     */
    protected function alsoDue()
    {
        if ($this->clientId || $this->leadId || $this->projectId || ! Gate::allows('sales.manage')) {
            return collect();
        }
        $today = today_local()->toDateString();
        $mine = $this->assignee === 'me';

        return Lead::query()->open()->whereNotNull('next_action_date')->where('next_action_date', '<=', $today)
            ->when($mine, fn ($q) => $q->where('owner_id', auth()->id()))->with('owner')->get()
            ->map(fn (Lead $l) => ['kind' => 'Lead next action', 'title' => $l->next_action ?: 'Follow up', 'about' => $l->name, 'who' => $l->owner?->name, 'date' => $l->next_action_date, 'url' => route('leads.show', $l)])
            ->concat(Quote::query()->whereIn('status', ['draft', 'sent'])->whereNotNull('follow_up_date')->where('follow_up_date', '<=', $today)
                ->when($mine, fn ($q) => $q->where('created_by', auth()->id()))->with(['client', 'lead', 'creator'])->get()
                ->map(fn (Quote $q) => ['kind' => 'Quote follow-up', 'title' => $q->number.' · '.$q->title, 'about' => $q->partyName(), 'who' => $q->creator?->name, 'date' => $q->follow_up_date, 'url' => route('quotes.show', $q)]))
            ->sortBy(fn ($i) => $i['date']->toDateString())->values();
    }

    public function render()
    {
        $today = today_local()->toDateString();

        $tasks = Task::query()->with(['client', 'lead', 'project', 'assignees'])
            ->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))
            ->when($this->leadId, fn ($q) => $q->where('lead_id', $this->leadId))
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->when(! $this->projectId && $this->project === 'none', fn ($q) => $q->whereNull('project_id'))
            ->when(! $this->projectId && ctype_digit($this->project), fn ($q) => $q->where('project_id', $this->project))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('title', 'like', "%{$s}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                ->orWhereHas('project', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                ->orWhereHas('lead', fn ($q) => $q->where('name', 'like', "%{$s}%"))))
            ->when($this->assignee, fn ($q, $v) => $q->assignedTo($v === 'me' ? auth()->id() : $v))
            ->when($this->status === 'open', fn ($q) => $q->open())
            ->when($this->status === 'due', fn ($q) => $q->open()->where('due_date', '<=', $today))
            ->when($this->status === 'overdue', fn ($q) => $q->open()->where('due_date', '<', $today))
            ->when(array_key_exists($this->status, Task::STATUSES), fn ($q) => $q->where('status', $this->status))
            ->orderByRaw("status = 'done'")->orderByRaw('due_date is null')->orderBy('due_date')->orderByDesc('id')
            ->paginate(25);

        $embedded = (bool) ($this->clientId || $this->leadId);
        $formClient = $this->form['for'] === 'client' ? ($this->form['client_id'] ?? null) : null;
        $projectAccess = Gate::allows('workspace') && Gate::allows('projects.view');
        $canSales = Gate::allows('sales.manage');

        return view('livewire.tasks.index', [
            'tasks' => $tasks,
            'embedded' => $embedded,
            'canManage' => $this->canManage(),
            'canSales' => $canSales,
            'inProject' => (bool) $this->projectId,
            'projectAccess' => $projectAccess,
            // Filter: projects that have tasks (inside a client profile, that client's only).
            'projectOptions' => $this->projectId || $this->leadId ? collect() : Project::query()->whereHas('tasks')
                ->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))->orderBy('name')->get(['id', 'name']),
            'formProjects' => $this->showForm && $formClient && ! $this->projectId
                ? Project::where('client_id', $formClient)->where(fn ($q) => $q->open()->orWhere('id', $this->form['project_id'] ?: 0))->orderBy('name')->get(['id', 'name']) : collect(),
            'alsoDue' => $this->alsoDue(),
            'users' => User::active()->where('role', '!=', User::ROLE_SUB_ADMIN)->orderBy('name')->get(['id', 'name']),
            'clients' => $this->showForm && ! $embedded ? Client::notArchived()->orderBy('name')->get(['id', 'name']) : collect(),
            'leads' => $this->showForm && ! $embedded && $canSales ? Lead::open()->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }
}
