<?php

namespace App\Livewire\Office;

use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Administration → Bank Details: the company's bank accounts, with buttons to copy one detail or all of
 * them for pasting into an email or message. Super Admins and Admins only; every change is written to the activity log.
 */
#[Title('Bank Details')]
class BankDetails extends Component
{
    protected const FIELDS = ['label', 'account_name', 'bank_name', 'account_number', 'ifsc', 'branch', 'account_type', 'swift', 'upi_id', 'notes'];

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function booted(): void
    {
        Gate::authorize('staff.manage');   // Super Admins and Admins
    }

    public function edit(?int $id = null): void
    {
        $this->resetValidation();
        $account = $id ? BankAccount::findOrFail($id) : null;
        $this->editingId = $account?->id;
        $this->form = collect(self::FIELDS)->mapWithKeys(fn ($field) => [$field => (string) ($account->{$field} ?? '')])->all();
        $this->form['account_type'] = $this->form['account_type'] ?: 'current';
        $this->form['is_default'] = $account->is_default ?? ! BankAccount::exists();
        $this->showForm = true;
    }

    public function save(): void
    {
        foreach (['account_number', 'ifsc', 'swift'] as $field) {
            $this->form[$field] = strtoupper(preg_replace('/\s+/', '', (string) ($this->form[$field] ?? '')));
        }
        $this->validate([
            'form.label' => ['required', 'string', 'max:255'],
            'form.account_name' => ['required', 'string', 'max:255'],
            'form.bank_name' => ['required', 'string', 'max:255'],
            'form.account_number' => ['required', 'regex:/^[0-9A-Z]{6,34}$/'],
            // Indian IFSC: four letters, a zero, then six letters or digits.
            'form.ifsc' => ['nullable', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'form.branch' => ['nullable', 'string', 'max:255'],
            'form.account_type' => ['required', Rule::in(array_keys(BankAccount::TYPES))],
            'form.swift' => ['nullable', 'regex:/^[A-Z0-9]{8}([A-Z0-9]{3})?$/'],
            'form.upi_id' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._\-]{2,}@[A-Za-z0-9.\-]{2,}$/'],
            'form.notes' => ['nullable', 'string', 'max:500'],
            'form.is_default' => ['boolean'],
        ], [
            'form.account_number.regex' => 'Enter the account number using digits (and letters, if it has any), without spaces.',
            'form.ifsc.regex' => 'An IFSC code has 11 characters, for example HDFC0001234.',
            'form.swift.regex' => 'A SWIFT code has 8 or 11 letters and digits.',
            'form.upi_id.regex' => 'A UPI ID looks like name@bank.',
        ], ['form.label' => 'name', 'form.account_name' => 'account name', 'form.bank_name' => 'bank', 'form.account_number' => 'account number',
            'form.ifsc' => 'IFSC code', 'form.branch' => 'branch', 'form.swift' => 'SWIFT code', 'form.upi_id' => 'UPI ID']);

        // Only the fields on the form.
        $data = collect($this->form)->only(self::FIELDS)->map(fn ($v) => trim((string) $v) === '' ? null : trim((string) $v))->all();
        $data['is_default'] = (bool) $this->form['is_default'];
        $data['updated_by'] = auth()->id();

        $account = DB::transaction(function () use ($data) {
            $account = $this->editingId ? tap(BankAccount::findOrFail($this->editingId))->update($data) : BankAccount::create($data);
            if ($account->is_default) {
                BankAccount::where('id', '!=', $account->id)->update(['is_default' => false]);
            } elseif (! BankAccount::where('is_default', true)->exists()) {
                $account->update(['is_default' => true]);   // there is always one default
            }

            return $account;
        });
        // Bank details decide where clients send money, so who changed them is always recorded.
        activity($this->editingId ? 'updated' : 'created', 'Bank details “'.$account->label.'” '.($this->editingId ? 'changed' : 'added').' by '.auth()->user()->name);

        $this->closeModal();
        $this->dispatch('toast', message: 'Bank details saved.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('admin');   // only a Super Admin removes an account
        $account = BankAccount::findOrFail($id);
        $account->delete();
        if ($account->is_default && ($next = BankAccount::orderBy('id')->first())) {
            $next->update(['is_default' => true]);
        }
        activity('deleted', 'Bank details “'.$account->label.'” deleted by '.auth()->user()->name);
        $this->dispatch('toast', message: 'Bank details deleted.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function render()
    {
        return view('livewire.office.bank-details', [
            'accounts' => BankAccount::orderByDesc('is_default')->orderBy('label')->get(),
            'canDelete' => Gate::allows('admin'),
        ]);
    }
}
