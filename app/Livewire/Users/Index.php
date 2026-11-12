<?php

namespace App\Livewire\Users;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    /** Newly chosen headshot (temporary upload). */
    public $photo;

    public bool $removePhoto = false;

    /**
     * Which accounts this screen manages, set by the route:
     *   "admins" (Administration → Users): Super Admins and Admins. Super Admins only.
     *   "staff"  (Administration → Staff): Staff accounts and their access. Super Admins and Admins.
     */
    #[Locked]
    public string $scope = 'admins';

    /** super_admin | sub_admin on the Users screen; always staff on the Staff screen. */
    #[Url(except: 'super_admin')]
    public string $role = 'super_admin';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    /** Shown once after an account is created: who, the login link and (for invitations) the invitation link. */
    public ?array $created = null;

    public function mount(string $scope = 'admins'): void
    {
        $this->scope = $scope === 'staff' ? 'staff' : 'admins';
        $this->role = $this->scope === 'staff' ? User::ROLE_STAFF : (in_array($this->role, $this->roles(), true) ? $this->role : User::ROLE_SUPER_ADMIN);
        $this->resetForm();
    }

    public function booted(): void
    {
        Gate::authorize($this->scope === 'staff' ? 'staff.manage' : 'admin');
    }

    /** @return string[] the roles this screen may list, create and change */
    protected function roles(): array
    {
        return $this->scope === 'staff' ? [User::ROLE_STAFF] : [User::ROLE_SUPER_ADMIN, User::ROLE_SUB_ADMIN];
    }

    public function updatedRole(): void
    {
        if (! in_array($this->role, $this->roles(), true)) {
            $this->role = $this->roles()[0];
        }
    }

    /** One of the accounts this screen manages; anything else is refused. */
    protected function target(int $id): User
    {
        return User::whereIn('role', $this->roles())->find($id) ?? abort(403);
    }

    protected function resetForm(): void
    {
        $this->form = [
            'name' => '', 'email' => '', 'phone' => '', 'alt_phone' => '', 'date_of_birth' => '',
            'role' => in_array($this->role, $this->roles(), true) ? $this->role : $this->roles()[0], 'is_active' => true,
            'password' => '',
            'permissions' => ['clients.view', 'tasks.manage'],
        ];
        $this->editingId = null;
        $this->photo = null;
        $this->removePhoto = false;
    }

    public function create(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = $this->target($id);
        $this->resetValidation();
        $this->photo = null;
        $this->removePhoto = false;
        $this->editingId = $user->id;
        $this->form = [
            'name' => $user->name, 'email' => $user->email, 'phone' => (string) $user->phone, 'alt_phone' => (string) $user->alt_phone,
            'date_of_birth' => $user->date_of_birth?->format('Y-m-d') ?? '',
            'role' => $user->role, 'is_active' => $user->is_active, 'permissions' => $user->permissions ?? [],
            'password' => '',
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.alt_phone' => ['nullable', 'string', 'max:30'],
            'form.date_of_birth' => ['nullable', 'date', 'before:today', 'after:1920-01-01'],
            'form.role' => ['required', Rule::in($this->roles())],
            'form.is_active' => ['boolean'],
            // Optional: leave empty to send an invitation (new user) or keep the current password (existing user).
            'form.password' => ['nullable', 'string', Password::min(8), 'max:200'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'form.permissions' => ['array'],
            'form.permissions.*' => ['string', Rule::in(array_keys(User::STAFF_PERMISSIONS))],
        ], [], ['form.name' => 'name', 'form.email' => 'email', 'form.phone' => 'primary phone', 'form.alt_phone' => 'alternate phone', 'form.date_of_birth' => 'date of birth', 'form.password' => 'password']);

        $password = (string) ($this->form['password'] ?? '');
        if ($password !== '' && $this->editingId === auth()->id()) {
            $this->addError('form.password', 'Use Change Password in your own account menu (top right) for your own password.');

            return;
        }

        $data = [
            'name' => $this->form['name'],
            'email' => strtolower($this->form['email']),
            'phone' => $this->form['phone'] ?: null,
            'alt_phone' => $this->form['alt_phone'] ?: null,
            'date_of_birth' => $this->form['date_of_birth'] ?: null,
            'role' => $this->form['role'],
            'is_active' => (bool) $this->form['is_active'],
        ];
        // Access: only options this person may give for the chosen role are taken from the form. Anything the
        // account already has that they may not change (an area an Admin was not given) is left exactly as it was.
        $mayGive = auth()->user()->assignablePermissions($data['role']);
        $kept = $this->editingId ? array_diff(array_intersect(User::grantable($data['role']), $this->target($this->editingId)->permissions ?? []), $mayGive) : [];
        $data['permissions'] = $data['role'] === User::ROLE_SUPER_ADMIN ? null
            : array_values(array_intersect(User::grantable($data['role']), array_merge(array_intersect($mayGive, $this->form['permissions']), $kept)));

        if ($this->editingId) {
            $user = $this->target($this->editingId);
            if ($message = $this->guardLastAdmin($user, $data['role'], $data['is_active'])) {
                $this->addError('form.role', $message);

                return;
            }
            $user->update($data);
            if ($password !== '') {
                // Their other sessions end automatically because the stored password changed.
                $user->forceFill(['password' => $password, 'invitation_token' => null])->save();
                activity('password_set', "Password set for {$user->email} by a Super Admin", $user);
            }
            $this->dispatch('toast', message: $password !== '' ? 'User updated and password changed.' : 'User updated.');
        } else {
            $user = User::create($data + ['invited_by' => auth()->id()]);
            $inviteUrl = null;
            if ($password !== '') {
                // Ready to use straight away: no invitation needed.
                $user->forceFill(['password' => $password, 'email_verified_at' => now()])->save();
                activity('password_set', "Account for {$user->email} created with a password by a Super Admin", $user);
            } else {
                $inviteUrl = route('invitation.show', $this->sendInvitation($user));
            }
            $this->created = [
                'name' => $user->name, 'email' => $user->email, 'role' => $user->role_label,
                'login_url' => $user->loginUrl(), 'invite_url' => $inviteUrl,
            ];
        }

        if ($this->photo || $this->removePhoto) {
            if ($user->avatar_path) {
                Storage::disk('local')->delete($user->avatar_path);
            }
            $user->update(['avatar_path' => $this->photo ? $this->photo->store('avatars', 'local') : null]);
        }

        $this->role = $user->role;
        $this->showForm = false;
        $this->resetForm();
    }

    public function resend(int $id): void
    {
        $user = $this->target($id);
        abort_unless($user->hasPendingInvitation(), 422);
        $this->created = [
            'name' => $user->name, 'email' => $user->email, 'role' => $user->role_label,
            'login_url' => $user->loginUrl(), 'invite_url' => route('invitation.show', $this->sendInvitation($user)),
        ];
    }

    /** For someone who lost their phone: clears two-step login so they can sign in with the password and set it up again. */
    public function resetTwoFactor(int $id): void
    {
        $user = $this->target($id);
        abort_if($user->id === auth()->id(), 422);
        $user->clearTwoFactor();
        activity('two_factor', "Two-step login reset for {$user->email} by a Super Admin", $user);
        $this->dispatch('toast', message: "Two-step login reset for {$user->name}.");
    }

    /** Applies to Super Admins only. Whoever switches it on must already have it, so nobody is locked out by surprise. */
    public function setRequireTwoFactor(bool $on): void
    {
        abort_unless(auth()->user()->isSuperAdmin() && $this->scope === 'admins', 403);
        if ($on && ! auth()->user()->hasTwoFactor()) {
            $this->dispatch('toast', message: 'Set up two-step login on your own account first (top-right menu → Two-step login).', type: 'error');

            return;
        }
        Setting::put('require_two_factor', $on ? '1' : '0');
        activity('updated', 'Two-step login for Super Admins '.($on ? 'made required' : 'made optional'));
        $this->dispatch('toast', message: $on ? 'Two-step login is now required for Super Admins.' : 'Two-step login is now optional.');
    }

    public function toggleActive(int $id): void
    {
        $user = $this->target($id);
        if ($message = $this->guardLastAdmin($user, $user->role, ! $user->is_active)) {
            $this->dispatch('toast', message: $message, type: 'error');

            return;
        }
        // Deactivation never deletes: payments, tasks and logs keep pointing at this user.
        $user->update(['is_active' => ! $user->is_active]);
        $this->dispatch('toast', message: $user->name.($user->is_active ? ' reactivated.' : ' deactivated. Their history is kept.'));
    }

    /** Emails the invitation and returns the one-time token so the link can be shown to the admin once. */
    protected function sendInvitation(User $user): string
    {
        $token = Str::random(48);
        $user->forceFill(['invitation_token' => hash('sha256', $token), 'invited_at' => now()])->saveQuietly();
        $user->notify(new UserInvited($token, auth()->user()->name));
        activity('invited', "Invitation sent to {$user->email}", $user);

        return $token;
    }

    public function dismissCreated(): void
    {
        $this->created = null;
    }

    protected function guardLastAdmin(User $user, string $newRole, bool $newActive): ?string
    {
        $losesAdmin = $user->isSuperAdmin() && $user->is_active && ($newRole !== User::ROLE_SUPER_ADMIN || ! $newActive);
        if (! $losesAdmin) {
            return null;
        }
        if ($user->id === auth()->id()) {
            return 'You cannot deactivate or demote your own account.';
        }
        $others = User::active()->superAdmins()->where('id', '!=', $user->id)->whereNotNull('password')->count();

        return $others === 0 ? 'At least one active Super Admin is required.' : null;
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        return view('livewire.users.index', [
            'users' => User::withCount('equipment')->where('role', in_array($this->role, $this->roles(), true) ? $this->role : $this->roles()[0])->orderByDesc('is_active')->orderBy('name')->get(),
            'counts' => collect(User::ROLES)->map(fn ($label, $role) => User::where('role', $role)->count())->all(),
            'staffScope' => $this->scope === 'staff',
            'roleTabs' => collect($this->roles())->mapWithKeys(fn ($role) => [$role => User::ROLES[$role].'s'])->all(),
            // What the signed-in person may tick for the role in the form.
            'mayGive' => auth()->user()->assignablePermissions($this->form['role'] ?? User::ROLE_STAFF),
        ])->title($this->scope === 'staff' ? 'Staff' : 'Users');
    }
}
