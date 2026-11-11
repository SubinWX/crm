<?php

namespace App\Livewire\Equipment;

use App\Models\Equipment;
use App\Models\Lookup;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Equipment issued to team members. Super Admins manage everything;
 * everyone else sees a read-only list of the items issued to them.
 */
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    /** '' | user id | unassigned */
    #[Url(except: '')]
    public string $user = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $condition = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'user', 'type', 'condition'], true)) {
            $this->resetPage();
        }
    }

    protected function resetForm(): void
    {
        $this->form = [
            'name' => '', 'type_id' => '', 'brand_model' => '', 'serial_number' => '',
            'user_id' => ctype_digit($this->user) ? $this->user : '', 'assigned_on' => today_local()->toDateString(),
            'purchase_date' => '', 'purchase_cost' => '', 'condition' => 'good', 'notes' => '',
        ];
        $this->editingId = null;
    }

    public function create(): void
    {
        Gate::authorize('admin');
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('admin');
        $item = Equipment::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $item->id;
        foreach (array_keys($this->form) as $key) {
            $value = $item->{$key};
            $this->form[$key] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) ($value ?? '');
        }
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('admin');
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.type_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', 'equipment_type')],
            'form.brand_model' => ['nullable', 'string', 'max:255'],
            'form.serial_number' => ['nullable', 'string', 'max:255'],
            'form.user_id' => ['nullable', 'exists:users,id'],
            'form.assigned_on' => ['nullable', 'date'],
            'form.purchase_date' => ['nullable', 'date'],
            'form.purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'form.condition' => ['required', Rule::in(array_keys(Equipment::CONDITIONS))],
            'form.notes' => ['nullable', 'string', 'max:2000'],
        ], [], collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace(['_id', '_'], ['', ' '], $k)])->all());

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        if (! $data['user_id']) {
            $data['assigned_on'] = null; // nothing is "assigned" while it sits in stock
        }

        $this->editingId
            ? Equipment::findOrFail($this->editingId)->update($data)
            : Equipment::create($data + ['created_by' => auth()->id()]);

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Equipment saved.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('admin');
        Equipment::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Equipment deleted.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        $admin = Gate::allows('admin');

        $query = Equipment::query()
            // Everyone except Super Admins is limited to their own items, whatever the filters say.
            ->when(! $admin, fn ($q) => $q->where('user_id', auth()->id()))
            ->when($admin && $this->user === 'unassigned', fn ($q) => $q->whereNull('user_id'))
            ->when($admin && ctype_digit($this->user), fn ($q) => $q->where('user_id', $this->user))
            ->when($this->type, fn ($q, $v) => $q->where('type_id', $v))
            ->when($this->condition, fn ($q, $v) => $q->where('condition', $v))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                ->orWhere('brand_model', 'like', "%{$s}%")->orWhere('serial_number', 'like', "%{$s}%")
                ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$s}%"))));

        return view('livewire.equipment.index', [
            'admin' => $admin,
            'items' => (clone $query)->with(['type', 'user'])->orderByRaw('user_id is null')->orderBy('name')->paginate(25),
            'totalValue' => $admin ? (float) (clone $query)->sum('purchase_cost') : null,
            // Per-person summary for the Super Admin view.
            'holders' => $admin ? User::query()->withCount('equipment')->having('equipment_count', '>', 0)->orderBy('name')->get() : collect(),
            'unassigned' => $admin ? Equipment::whereNull('user_id')->count() : 0,
            'users' => $admin ? User::query()->where(fn ($q) => $q->where('is_active', true)
                ->when($this->form['user_id'] ?? null, fn ($q, $id) => $q->orWhere('id', $id)))->orderBy('name')->get(['id', 'name', 'is_active', 'role']) : collect(),
            'types' => Lookup::options('equipment_type', $this->form['type_id'] ?? null),
        ])->title($admin ? 'Equipment' : 'My Equipment');
    }
}
