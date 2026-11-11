<?php

namespace App\Livewire\Invoices;

use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    public ?Invoice $invoice = null;

    public array $form = [];

    /** @var array<int, array{description:string, quantity:string, unit_price:string}> */
    public array $items = [];

    public function boot(): void
    {
        Gate::authorize('finance');
    }

    public function mount(?Invoice $invoice = null)
    {
        if ($invoice?->exists) {
            if ($invoice->status !== 'draft') {
                session()->flash('status', 'Only draft invoices can be edited. Void this one and create a new invoice if it is wrong.');

                $this->redirectRoute('invoices.show', $invoice);
            }
            $this->invoice = $invoice;
            $this->form = [
                'number' => $invoice->number, 'client_id' => (string) $invoice->client_id,
                'invoice_date' => $invoice->invoice_date->format('Y-m-d'),
                'planned_send_date' => $invoice->planned_send_date?->format('Y-m-d') ?? '',
                'due_date' => $invoice->due_date?->format('Y-m-d') ?? '',
                'currency' => $invoice->currency, 'tax_rate' => (string) (float) $invoice->tax_rate,
                'discount' => (string) (float) $invoice->discount, 'notes' => (string) $invoice->notes,
                'contact_id' => (string) ($invoice->contact_id ?? ''),
            ];
            $this->items = $invoice->items->map(fn ($i) => [
                'description' => $i->description, 'quantity' => (string) (float) $i->quantity, 'unit_price' => (string) (float) $i->unit_price,
            ])->all();
        } else {
            $this->invoice = null;
            $today = today_local();
            $this->form = [
                'number' => Invoice::nextNumber(),
                'client_id' => (string) (Client::whereKey(request()->query('client'))->value('id') ?? ''),
                'invoice_date' => $today->toDateString(),
                'planned_send_date' => $today->toDateString(),
                'due_date' => $today->copy()->addDays((int) setting('invoice_due_days', 15))->toDateString(),
                'currency' => setting('currency', 'INR'),
                'tax_rate' => (string) setting('invoice_default_tax_rate', 0),
                'discount' => '0', 'notes' => '', 'contact_id' => '',
            ];
        }

        if ($this->items === []) {
            $this->addItem();
        }
    }

    public function updatedFormClientId(): void
    {
        $this->form['contact_id'] = '';
    }

    public function addItem(): void
    {
        $this->items[] = ['description' => '', 'quantity' => '1', 'unit_price' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        if ($this->items === []) {
            $this->addItem();
        }
    }

    public function totals(): array
    {
        $subtotal = 0;
        foreach ($this->items as $item) {
            $subtotal += round((float) ($item['quantity'] ?: 0) * (float) ($item['unit_price'] ?: 0), 2);
        }
        $discount = min(max((float) ($this->form['discount'] ?: 0), 0), $subtotal);
        $tax = round(($subtotal - $discount) * (float) ($this->form['tax_rate'] ?: 0) / 100, 2);

        return ['subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'total' => round($subtotal - $discount + $tax, 2)];
    }

    public function save()
    {
        abort_if($this->invoice && $this->invoice->fresh()->status !== 'draft', 422, 'Only draft invoices can be edited.');

        $this->validate([
            'form.number' => ['required', 'string', 'max:40', Rule::unique('invoices', 'number')->ignore($this->invoice?->id)],
            'form.client_id' => ['required', 'exists:clients,id'],
            'form.invoice_date' => ['required', 'date'],
            'form.planned_send_date' => ['nullable', 'date'],
            'form.due_date' => ['nullable', 'date', 'after_or_equal:form.invoice_date'],
            'form.currency' => ['required', Rule::in(array_keys(SettingsIndex::CURRENCIES))],
            'form.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'form.discount' => ['nullable', 'numeric', 'min:0'],
            'form.notes' => ['nullable', 'string', 'max:2000'],
            // The recipient must be one of this client's saved contacts.
            'form.contact_id' => ['nullable', Rule::exists('client_contacts', 'id')->where('client_id', $this->form['client_id'] ?: 0)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ], [], [
            'form.number' => 'invoice number', 'form.client_id' => 'client', 'form.invoice_date' => 'invoice date',
            'form.planned_send_date' => 'planned send date', 'form.due_date' => 'due date', 'form.tax_rate' => 'tax rate',
            'items.*.description' => 'description', 'items.*.quantity' => 'quantity', 'items.*.unit_price' => 'unit price',
        ]);

        $invoice = DB::transaction(function () {
            $data = [
                'number' => $this->form['number'],
                'client_id' => $this->form['client_id'],
                'invoice_date' => $this->form['invoice_date'],
                'planned_send_date' => $this->form['planned_send_date'] ?: null,
                'due_date' => $this->form['due_date'] ?: null,
                'currency' => $this->form['currency'],
                'tax_rate' => $this->form['tax_rate'] ?: 0,
                'discount' => $this->form['discount'] ?: 0,
                'notes' => $this->form['notes'] ?: null,
            ];
            $contact = ($this->form['contact_id'] ?? '') !== '' ? ClientContact::find($this->form['contact_id']) : null;
            $data += ['contact_id' => $contact?->id, 'recipient_name' => $contact?->name, 'recipient_email' => $contact?->email];

            if ($this->invoice) {
                $invoice = $this->invoice;
                $invoice->update($data);
                $invoice->items()->delete();
            } else {
                $invoice = Invoice::create($data + ['status' => 'draft', 'created_by' => auth()->id()]);
            }

            foreach ($this->items as $index => $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'amount' => round((float) $item['quantity'] * (float) $item['unit_price'], 2),
                    'sort_order' => $index,
                ]);
            }
            $invoice->recalculateTotals();

            return $invoice;
        });

        session()->flash('status', 'Draft invoice '.$invoice->number.' saved.');

        return $this->redirectRoute('invoices.show', $invoice);
    }

    public function render()
    {
        return view('livewire.invoices.form', [
            'clients' => Client::query()->where(fn ($q) => $q->whereNull('archived_at')
                ->when($this->form['client_id'] ?? null, fn ($q, $id) => $q->orWhere('id', $id)))->orderBy('name')->get(['id', 'name']),
            'contacts' => ($this->form['client_id'] ?? '') !== '' ? ClientContact::where('client_id', $this->form['client_id'])->orderByDesc('is_primary')->orderBy('name')->get() : collect(),
            'totals' => $this->totals(),
        ])->title($this->invoice ? 'Edit '.$this->invoice->number : 'New Invoice');
    }
}
