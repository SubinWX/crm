<?php

namespace App\Livewire\Account;

use App\Models\User;
use App\Support\LeaveBalance;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A person's profile: their details and the devices issued to them. (Rules & Policies, Meeting
 * Notes and Documents are under Office.) Everyone has their own at /profile.
 * Super Admins (anyone), Admins and leave managers (staff) can open someone else's.
 */
#[Title('Profile')]
class Profile extends Component
{
    public const TABS = ['details' => 'Details', 'devices' => 'Allocated Devices'];

    /** Details the person (or whoever manages their profile) fills in. Name, work email and role are changed in Administration. */
    protected const PERSONAL = ['phone', 'alt_phone', 'date_of_birth', 'gender', 'personal_email', 'address', 'city', 'state', 'pincode', 'nationality', 'blood_group'];

    protected const EMERGENCY = ['emergency_name', 'emergency_relation', 'emergency_phone', 'emergency_alt_phone'];

    #[Locked]
    public int $userId;

    #[Url(except: 'details')]
    public string $tab = 'details';

    /** null | personal | emergency */
    public ?string $modal = null;

    public array $form = [];

    public ?int $editingId = null;

    public function mount(?User $user = null): void
    {
        $this->userId = $user?->exists ? $user->id : auth()->id();
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'details';
        }
    }

    public function booted(): void
    {
        abort_unless($this->own() || auth()->user()->managesProfileOf($this->person()), 403);
    }

    protected function person(): User
    {
        return User::findOrFail($this->userId);
    }

    protected function own(): bool
    {
        return $this->userId === auth()->id();
    }

    /** Someone else's profile that the signed-in person looks after. */
    protected function manages(): bool
    {
        return auth()->user()->managesProfileOf($this->person());
    }

    public function updatedTab(): void
    {
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'details';
        }
    }

    public function closeModal(): void
    {
        $this->reset(['modal', 'form', 'editingId']);
        $this->resetValidation();
    }

    /* ---------- Details ---------- */

    public function editDetails(string $section): void
    {
        $this->closeModal();
        $person = $this->person();
        $fields = $section === 'emergency' ? self::EMERGENCY : array_merge(self::PERSONAL, $this->canSetJoining() ? ['joined_on'] : []);
        foreach ($fields as $field) {
            $value = $person->{$field};
            $this->form[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) ($value ?? '');
        }
        $this->modal = $section === 'emergency' ? 'emergency' : 'personal';
    }

    /** The joining date decides leave allowances, so only Super Admins and whoever manages the profile set it. */
    protected function canSetJoining(): bool
    {
        return auth()->user()->isSuperAdmin() || $this->manages();
    }

    public function saveDetails(): void
    {
        $emergency = $this->modal === 'emergency';
        abort_unless(in_array($this->modal, ['personal', 'emergency'], true), 404);

        $rules = $emergency ? [
            'form.emergency_name' => ['nullable', 'string', 'max:255'],
            'form.emergency_relation' => ['nullable', 'string', 'max:100'],
            'form.emergency_phone' => ['nullable', 'string', 'max:30'],
            'form.emergency_alt_phone' => ['nullable', 'string', 'max:30'],
        ] : [
            'form.phone' => ['nullable', 'string', 'max:30'],
            'form.alt_phone' => ['nullable', 'string', 'max:30'],
            'form.date_of_birth' => ['nullable', 'date', 'before:today'],
            'form.gender' => ['nullable', Rule::in(array_keys(User::GENDERS))],
            'form.personal_email' => ['nullable', 'email', 'max:255'],
            'form.address' => ['nullable', 'string', 'max:500'],
            'form.city' => ['nullable', 'string', 'max:100'],
            'form.state' => ['nullable', 'string', 'max:100'],
            'form.pincode' => ['nullable', 'string', 'max:12'],
            'form.nationality' => ['nullable', 'string', 'max:100'],
            'form.blood_group' => ['nullable', Rule::in(User::BLOOD_GROUPS)],
        ] + ($this->canSetJoining() ? ['form.joined_on' => ['nullable', 'date', 'before_or_equal:today']] : []);

        $this->validate($rules, [], collect($rules)->keys()->mapWithKeys(fn ($k) => [$k => str_replace(['form.', 'emergency_', 'alt_', '_'], ['', '', 'alternate ', ' '], $k)])->all());

        // Only the fields of the section being saved, whatever else was sent.
        $fields = array_map(fn ($k) => substr($k, 5), array_keys($rules));
        $this->person()->update(collect($this->form)->only($fields)->map(fn ($v) => $v === '' ? null : $v)->all());

        $this->closeModal();
        $this->dispatch('toast', message: 'Details saved.');
    }

    public function render()
    {
        $person = $this->person();
        $manages = $this->manages();
        $year = (int) today_local()->format('Y');
        // Leave is for the working team; Admins are outside it.
        $balances = $person->isSubAdmin() ? collect() : LeaveBalance::for($person, $year);

        return view('livewire.account.profile', [
            'person' => $person,
            'own' => $this->own(),
            'manages' => $manages,
            'canSetJoining' => $this->canSetJoining(),
            'year' => $year,
            'balances' => $balances,
            'leaveTaken' => (float) $balances->sum('used'),
            'devices' => $this->tab === 'devices' ? $person->equipment()->with('type')->orderBy('name')->get() : collect(),
            'deviceCount' => $person->equipment()->count(),
        ])->title($this->own() ? 'My Profile' : $person->name);
    }
}
