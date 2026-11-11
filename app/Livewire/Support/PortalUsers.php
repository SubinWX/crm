<?php

namespace App\Livewire\Support;

use App\Models\Client;
use App\Models\PortalUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The portal's own login list. Super Admins manage every portal account;
 * Sub Admins can add accounts and manage the ones they added.
 */
#[Title('Portal Users')]
class PortalUsers extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    /** Shown once after an account is created. */
    public ?array $created = null;

    public function boot(): void
    {
        Gate::authorize('portal-users.manage');
    }

    public function mount(): void
    {
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    protected function resetForm(): void
    {
        $this->form = ['name' => '', 'email' => '', 'phone' => '', 'company' => '', 'client_id' => '', 'password' => '', 'is_active' => true];
        $this->editingId = null;
    }

    protected function find(int $id): PortalUser
    {
        return PortalUser::query()->visibleTo(auth()->user())->find($id) ?? abort(404);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = $this->find($id);
        $this->resetValidation();
        $this->editingId = $user->id;
        $this->form = [
            'name' => $user->name, 'email' => $user->email, 'phone' => (string) $user->phone, 'company' => (string) $user->company,
            'client_id' => (string) ($user->client_id ?? ''), 'password' => '', 'is_active' => $user->is_active,
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255', Rule::unique('portal_users', 'email')->ignore($this->editingId)],
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.company' => ['nullable', 'string', 'max:255'],
            // Sub Admins may only link clients they added.
            'form.client_id' => ['nullable', function ($attribute, $value, $fail) {
                if (! Client::query()->visibleTo(auth()->user())->whereKey($value)->exists()) {
                    $fail('Choose one of the available clients.');
                }
            }],
            // A new account needs a password (the person can also use Google or "Forgot password").
            'form.password' => [$this->editingId ? 'nullable' : 'required', 'string', Password::min(8), 'max:200'],
            'form.is_active' => ['boolean'],
        ], [], ['form.name' => 'name', 'form.email' => 'email', 'form.password' => 'password', 'form.client_id' => 'client']);

        $data = [
            'name' => $this->form['name'],
            'email' => Str::lower($this->form['email']),
            'phone' => $this->form['phone'] ?: null,
            'company' => $this->form['company'] ?: null,
            'client_id' => $this->form['client_id'] ?: null,
            'is_active' => (bool) $this->form['is_active'],
        ];
        if ($this->form['password'] !== '') {
            $data['password'] = $this->form['password'];
        }

        if ($this->editingId) {
            $this->find($this->editingId)->update($data);
            $this->dispatch('toast', message: 'Portal user updated.');
        } else {
            $user = PortalUser::create($data + ['created_by' => auth()->id(), 'email_verified_at' => now()]);
            activity('created', "Portal account created for {$user->email}", $user);
            $this->created = ['name' => $user->name, 'email' => $user->email, 'login_url' => route('portal.login')];
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $user = $this->find($id);
        $user->update(['is_active' => ! $user->is_active]);
        $this->dispatch('toast', message: $user->name.($user->is_active ? ' can sign in again.' : ' can no longer sign in. Their tickets are kept.'));
    }

    public function dismissCreated(): void
    {
        $this->created = null;
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        return view('livewire.support.portal-users', [
            'users' => PortalUser::query()->visibleTo(auth()->user())
                ->with(['client', 'creator'])->withCount('tickets')
                ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")->orWhere('company', 'like', "%{$s}%")))
                ->latest()->latest('id')->paginate(25),
            'clients' => $this->showForm ? Client::query()->visibleTo(auth()->user())->notArchived()->orderBy('name')->get(['id', 'name']) : collect(),
            'canSeeTickets' => Gate::allows('support.manage'),
        ]);
    }
}
