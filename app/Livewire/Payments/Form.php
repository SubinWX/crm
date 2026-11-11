<?php

namespace App\Livewire\Payments;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lookup;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Record a payment and apply it to one or more of the client's invoices.
 * Anything not applied stays on the client as an unallocated payment.
 */
class Form extends Component
{
    public ?Payment $payment = null;

    public array $form = [];

    /** @var array<int|string, string> invoice id => amount to apply */
    public array $allocations = [];

    public function boot(): void
    {
        Gate::authorize('finance');
    }

    public function mount(?Payment $payment = null): void
    {
        if ($payment?->exists) {
            $this->payment = $payment;
            $this->form = [
                'client_id' => (string) $payment->client_id,
                'payment_method_id' => (string) ($payment->payment_method_id ?? ''),
                'amount' => (string) (float) $payment->amount,
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'received_by' => (string) ($payment->received_by ?? ''),
                'reference' => (string) $payment->reference,
                'notes' => (string) $payment->notes,
            ];
            $this->allocations = $payment->allocations()->pluck('amount', 'invoice_id')
                ->map(fn ($a) => (string) (float) $a)->all();
        } else {
            $this->payment = null;
            $this->form = [
                'client_id' => (string) (Client::whereKey(request()->query('client'))->value('id') ?? ''),
                'payment_method_id' => '', 'amount' => '', 'payment_date' => today_local()->toDateString(),
                'received_by' => auth()->user()->isSuperAdmin() ? (string) auth()->id() : '',
                'reference' => '', 'notes' => '',
            ];

            // Coming from an invoice: prefill the balance against it.
            $invoice = Invoice::open()->where('client_id', $this->form['client_id'] ?: 0)->find(request()->query('invoice'));
            if ($invoice) {
                $this->form['amount'] = (string) $invoice->balance;
                $this->allocations[$invoice->id] = (string) $invoice->balance;
            }
        }
    }

    public function updatedFormClientId(): void
    {
        $this->allocations = [];
    }

    /** Invoices this payment can be applied to: open ones, plus any it is already applied to. */
    public function invoices()
    {
        if (! $this->form['client_id']) {
            return collect();
        }

        return Invoice::query()
            ->where('client_id', $this->form['client_id'])
            ->where(fn ($q) => $q->whereIn('status', Invoice::OPEN_STATUSES)
                ->when($this->payment, fn ($q) => $q->orWhereIn('id', $this->payment->allocations()->select('invoice_id'))))
            ->orderBy('invoice_date')->orderBy('id')
            ->get()
            ->each(function (Invoice $invoice) {
                $mine = $this->payment ? (float) $this->payment->allocations()->where('invoice_id', $invoice->id)->sum('amount') : 0;
                // Balance available to this payment (its own current allocation is added back).
                $invoice->available = round($invoice->balance + $mine, 2);
            });
    }

    /** Apply the payment to the oldest invoices first. */
    public function autoAllocate(): void
    {
        $remaining = round((float) ($this->form['amount'] ?: 0), 2);
        $this->allocations = [];
        foreach ($this->invoices() as $invoice) {
            if ($remaining <= 0) {
                break;
            }
            $apply = min($remaining, $invoice->available);
            if ($apply > 0) {
                $this->allocations[$invoice->id] = (string) $apply;
                $remaining = round($remaining - $apply, 2);
            }
        }
    }

    public function clearAllocations(): void
    {
        $this->allocations = [];
    }

    public function allocatedTotal(): float
    {
        return round(collect($this->allocations)->sum(fn ($a) => (float) ($a ?: 0)), 2);
    }

    public function save()
    {
        $this->validate([
            'form.client_id' => ['required', 'exists:clients,id'],
            'form.payment_method_id' => ['required', Rule::exists('lookups', 'id')->where('type', 'payment_method')],
            'form.amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'form.payment_date' => ['required', 'date', 'before_or_equal:'.today_local()->addDay()->toDateString()],
            // New payments must go to an active Super Admin; an existing payment may keep
            // its original recipient even if that user has since been deactivated.
            'form.received_by' => ['required', function ($attribute, $value, $fail) {
                $valid = User::superAdmins()->whereKey($value)
                    ->where(fn ($q) => $q->where('is_active', true)
                        ->when($this->payment, fn ($q) => $q->orWhere('id', $this->payment->received_by)))
                    ->exists();
                if (! $valid) {
                    $fail('Payment To must be an active Super Admin.');
                }
            }],
            'form.reference' => ['nullable', 'string', 'max:255'],
            'form.notes' => ['nullable', 'string', 'max:2000'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'form.client_id' => 'client', 'form.payment_method_id' => 'payment type', 'form.amount' => 'amount received',
            'form.payment_date' => 'payment date', 'form.received_by' => 'Payment To', 'allocations.*' => 'amount to apply',
        ]);

        if ($this->allocatedTotal() > round((float) $this->form['amount'], 2)) {
            $this->addError('allocations', 'You are applying '.money($this->allocatedTotal()).' but the payment is only '.money($this->form['amount']).'.');

            return null;
        }

        $payment = DB::transaction(function () {
            $data = [
                'client_id' => $this->form['client_id'],
                'payment_method_id' => $this->form['payment_method_id'],
                'amount' => $this->form['amount'],
                'payment_date' => $this->form['payment_date'],
                'received_by' => $this->form['received_by'],
                'reference' => $this->form['reference'] ?: null,
                'notes' => $this->form['notes'] ?: null,
            ];

            if ($this->payment) {
                $payment = $this->payment;
                if ((int) $data['client_id'] !== (int) $payment->client_id) {
                    $payment->syncAllocations([]);
                }
                $payment->update($data);
            } else {
                $payment = Payment::create($data + ['recorded_by' => auth()->id()]);
            }

            $payment->syncAllocations($this->allocations);

            return $payment;
        });

        $left = $payment->fresh()->unallocated;
        session()->flash('status', 'Payment of '.money($payment->amount).' saved.'.($left > 0 ? ' '.money($left).' is unallocated.' : ''));

        return $this->redirectRoute('clients.show', ['client' => $payment->client_id, 'tab' => 'finance']);
    }

    public function delete()
    {
        abort_unless($this->payment, 404);
        $clientId = $this->payment->client_id;

        DB::transaction(function () {
            $this->payment->syncAllocations([]);
            $this->payment->delete();
        });

        session()->flash('status', 'Payment deleted and invoice balances restored.');

        return $this->redirectRoute('clients.show', ['client' => $clientId, 'tab' => 'finance']);
    }

    public function render()
    {
        return view('livewire.payments.form', [
            'clients' => Client::query()->where(fn ($q) => $q->whereNull('archived_at')
                ->when($this->form['client_id'], fn ($q, $id) => $q->orWhere('id', $id)))->orderBy('name')->get(['id', 'name']),
            'methods' => Lookup::options('payment_method', $this->payment?->payment_method_id),
            'superAdmins' => User::superAdmins()->where(fn ($q) => $q->where('is_active', true)
                ->when($this->payment?->received_by, fn ($q, $id) => $q->orWhere('id', $id)))->orderBy('name')->get(),
            'invoices' => $this->invoices(),
            'allocated' => $this->allocatedTotal(),
        ])->title($this->payment ? 'Payment' : 'Record Payment');
    }
}
