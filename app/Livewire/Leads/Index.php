<?php

namespace App\Livewire\Leads;

use App\Models\Lead;
use App\Models\Lookup;
use App\Models\User;
use App\Support\LeadFilters;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Leads')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** open | all | one of Lead::STAGES */
    #[Url(except: 'open')]
    public string $stage = 'open';

    #[Url(except: '')]
    public string $owner = '';

    #[Url(except: '')]
    public string $source = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public array $serviceIds = [];

    public function boot(): void
    {
        Gate::authorize('sales.manage');
    }

    public function mount(): void
    {
        $this->resetForm();
        $this->showForm = request()->boolean('new');
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'stage', 'owner', 'source'], true)) {
            $this->resetPage();
        }
    }

    public function filters(): array
    {
        return ['search' => $this->search, 'stage' => $this->stage, 'owner' => $this->owner, 'source' => $this->source];
    }

    protected function resetForm(): void
    {
        $this->form = [
            'name' => '', 'contact_name' => '', 'email' => '', 'phone' => '', 'website' => '', 'location' => '',
            'acquisition_source_id' => '', 'estimated_value' => '', 'owner_id' => (string) auth()->id(), 'stage' => 'new',
            'next_action' => '', 'next_action_date' => '', 'notes' => '', 'lost_reason' => '',
        ];
        $this->serviceIds = [];
        $this->editingId = null;
    }

    public function create(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $lead = Lead::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $lead->id;
        foreach (array_keys($this->form) as $key) {
            $value = $lead->{$key};
            $this->form[$key] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) ($value ?? '');
        }
        $this->serviceIds = $lead->services()->pluck('lookups.id')->map(fn ($id) => (int) $id)->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.contact_name' => ['nullable', 'string', 'max:255'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.website' => ['nullable', 'string', 'max:255'],
            'form.location' => ['nullable', 'string', 'max:255'],
            'form.acquisition_source_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', 'acquisition_source')],
            'form.estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'form.owner_id' => ['nullable', 'exists:users,id'],
            'form.stage' => ['required', Rule::in(array_keys(Lead::STAGES))],
            'form.next_action' => ['nullable', 'string', 'max:255'],
            'form.next_action_date' => ['nullable', 'date', 'required_with:form.next_action'],
            'form.notes' => ['nullable', 'string', 'max:20000'],
            'form.lost_reason' => ['nullable', 'string', 'max:255'],
            'serviceIds.*' => [Rule::exists('lookups', 'id')->where('type', 'work_type')],
        ], ['form.next_action_date.required_with' => 'Give the next action a due date.'],
            collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace(['_id', '_'], ['', ' '], $k)])->all());

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();

        $lead = $this->editingId ? Lead::findOrFail($this->editingId) : new Lead(['created_by' => auth()->id()]);
        $lead->fill($data)->save();
        $lead->services()->sync($this->serviceIds);

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Lead saved.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        $counts = Lead::query()->selectRaw('stage, count(*) as total, coalesce(sum(estimated_value), 0) as value')->groupBy('stage')->get()->keyBy('stage');

        return view('livewire.leads.index', [
            'leads' => LeadFilters::apply(Lead::query()->with(['owner', 'source', 'services', 'client']), $this->filters())
                ->orderByRaw('next_action_date is null')->orderBy('next_action_date')->latest('id')->paginate(25),
            'counts' => $counts,
            'owners' => User::active()->where('role', '!=', User::ROLE_SUB_ADMIN)->orderBy('name')->get(['id', 'name']),
            'sources' => Lookup::options('acquisition_source', $this->form['acquisition_source_id'] ?? null),
            'services' => Lookup::options('work_type', $this->serviceIds),
        ]);
    }
}
