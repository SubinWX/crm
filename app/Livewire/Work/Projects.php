<?php

namespace App\Livewire\Work;

use App\Models\Client;
use App\Models\Project;
use App\Models\Quote;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

    /** User ids of the people on the project being added or edited. */
    public array $members = [];

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
            'description' => '', 'start_date' => '', 'estimated_hours' => '',
        ];
        $this->members = [];
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
        $this->form['estimated_hours'] = $project->estimated_hours !== null ? (string) (float) $project->estimated_hours : '';
        $this->members = $project->members()->pluck('users.id')->map(fn ($id) => (string) $id)->all();
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
            'form.description' => ['nullable', 'string', 'max:30000'],
            'form.start_date' => ['nullable', 'date'],
            'form.estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'members' => ['array'],
            'members.*' => [Rule::exists('users', 'id')->where('is_active', true)->whereNot('role', User::ROLE_SUB_ADMIN)],
        ], ['form.quote_id.exists' => 'Choose an accepted quote that belongs to this client.'],
            collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace(['_id', '_'], ['', ' '], $k)])->all());

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $project = $this->editingId ? tap($this->find($this->editingId))->update($data) : Project::create($data);
        $project->members()->sync(array_map('intval', $this->members));

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

    /**
     * Time tracked by each person: today, this week, this month and in total, with the projects
     * their time went to this month. Shown on the main Projects page.
     *
     * @return array{people: Collection, totals: array<string, int>}
     */
    protected function trackedByPerson(): array
    {
        $today = today_local();
        [$day, $week, $month] = [$today->toDateString(), $today->copy()->startOfWeek(Carbon::MONDAY)->toDateString(), $today->copy()->startOfMonth()->toDateString()];

        $people = TimeEntry::query()->finished()->toBase()
            ->join('users', 'users.id', '=', 'time_entries.user_id')
            ->selectRaw('users.id, users.name,
                sum(case when time_entries.work_date = ? then time_entries.minutes else 0 end) as today,
                sum(case when time_entries.work_date >= ? then time_entries.minutes else 0 end) as week,
                sum(case when time_entries.work_date >= ? then time_entries.minutes else 0 end) as month,
                sum(time_entries.minutes) as total', [$day, $week, $month])
            ->groupBy('users.id', 'users.name')->orderByDesc('month')->orderByDesc('total')->get();

        // Where each person's time went this month, largest first.
        $split = TimeEntry::query()->finished()->toBase()
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->where('time_entries.work_date', '>=', $month)
            ->selectRaw('time_entries.user_id, projects.id as project_id, projects.name, sum(time_entries.minutes) as minutes')
            ->groupBy('time_entries.user_id', 'projects.id', 'projects.name')->orderByDesc('minutes')->get()->groupBy('user_id');
        $running = TimeEntry::running()->with(['project', 'user'])->get()->keyBy('user_id');
        // Someone whose only time so far is a timer still running is listed too, with nothing saved yet.
        foreach ($running as $userId => $entry) {
            if (! $people->contains('id', $userId) && $entry->user) {
                $people->push((object) ['id' => $userId, 'name' => $entry->user->name, 'today' => 0, 'week' => 0, 'month' => 0, 'total' => 0]);
            }
        }

        return [
            'people' => $people->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'today' => (int) $p->today, 'week' => (int) $p->week, 'month' => (int) $p->month, 'total' => (int) $p->total,
                'projects' => ($split[$p->id] ?? collect())->take(4), 'running' => $running[$p->id] ?? null]),
            'totals' => ['today' => (int) $people->sum('today'), 'week' => (int) $people->sum('week'), 'month' => (int) $people->sum('month'), 'total' => (int) $people->sum('total')],
        ];
    }

    public function render()
    {
        $projects = Project::query()->with(['client', 'owner', 'quote', 'members'])->withSum('timeEntries as logged_minutes', 'minutes')
            ->withCount(['tasks', 'tasks as open_tasks_count' => fn ($q) => $q->open()])
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
            'tracked' => $this->clientId ? null : $this->trackedByPerson(),
            'users' => User::active()->where('role', '!=', User::ROLE_SUB_ADMIN)->orderBy('name')->get(['id', 'name']),
            'clients' => $this->showForm && ! $this->clientId ? Client::notArchived()->orderBy('name')->get(['id', 'name']) : collect(),
            'quotes' => $this->showForm && $formClient ? Quote::where('client_id', $formClient)->where('status', 'accepted')->latest('id')->get(['id', 'number', 'title']) : collect(),
            'canManage' => Gate::allows('clients.manage'),
        ]);
    }
}
