<?php

namespace App\Livewire\Invoices;

use App\Models\Client;
use App\Models\RecurringInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Finance → Recurring Invoices: retainers and maintenance plans that bill themselves. Each template raises
 * a draft invoice on its date; opening this page (or the scheduler) raises any that are due.
 */
#[Title('Recurring Invoices')]
class Recurring extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    /** @var array<int, array{description: string, hsn_sac: string, quantity: string, unit_price: string}> */
    public array $items = [];

    public function booted(): void
    {
        Gate::authorize('finance');
    }

    public function mount(): void
    {
        if (($raised = RecurringInvoice::generateDue()) > 0) {
            session()->flash('status', $raised.' recurring '.str('invoice')->plural($raised).' raised as '.($raised === 1 ? 'a draft' : 'drafts').'. Check and send '.($raised === 1 ? 'it' : 'them').' from Invoices.');
        }
    }

    public function addItem(): void
    {
        $this->items[] = ['description' => '', 'hsn_sac' => (string) setting('invoice_default_hsn', ''), 'quantity' => '1', 'unit_price' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        if ($this->items === []) {
            $this->addItem();
        }
    }

    public function edit(?int $id = null): void
    {
        $this->resetValidation();
        $template = $id ? RecurringInvoice::with('items')->findOrFail($id) : null;
        $this->editingId = $template?->id;
        $this->form = [
            'client_id' => (string) ($template->client_id ?? ''), 'name' => $template->name ?? '', 'frequency' => $template->frequency ?? 'monthly',
            'next_date' => ($template?->next_date ?? today_local()->addMonthNoOverflow()->startOfMonth())->format('Y-m-d'),
            'end_date' => $template?->end_date?->format('Y-m-d') ?? '', 'due_days' => (string) ($template->due_days ?? setting('invoice_due_days', 15)),
            'tax_rate' => (string) (float) ($template->tax_rate ?? setting('invoice_default_tax_rate', 0)), 'notes' => (string) ($template->notes ?? ''),
        ];
        $this->items = $template ? $template->items->map(fn ($i) => [
            'description' => $i->description, 'hsn_sac' => (string) $i->hsn_sac, 'quantity' => (string) (float) $i->quantity, 'unit_price' => (string) (float) $i->unit_price,
        ])->all() : [];
        if ($this->items === []) {
            $this->addItem();
        }
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.client_id' => ['required', 'exists:clients,id'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.frequency' => ['required', Rule::in(array_keys(RecurringInvoice::FREQUENCIES))],
            'form.next_date' => ['required', 'date'],
            'form.end_date' => ['nullable', 'date', 'after_or_equal:form.next_date'],
            'form.due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'form.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'form.notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.hsn_sac' => ['nullable', 'string', 'max:10'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
        ], [], ['form.client_id' => 'client', 'form.name' => 'name', 'form.next_date' => 'next invoice date', 'form.end_date' => 'last date', 'form.due_days' => 'days to pay',
            'form.tax_rate' => 'tax rate', 'items.*.description' => 'description', 'items.*.quantity' => 'quantity', 'items.*.unit_price' => 'price']);

        DB::transaction(function () {
            $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
            $template = $this->editingId
                ? tap(RecurringInvoice::findOrFail($this->editingId))->update($data)
                : RecurringInvoice::create($data + ['created_by' => auth()->id()]);
            $template->items()->delete();
            foreach ($this->items as $position => $item) {
                $template->items()->create(['description' => trim($item['description']), 'hsn_sac' => trim((string) $item['hsn_sac']) ?: null,
                    'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'sort_order' => $position]);
            }
        });

        $this->closeModal();
        $this->dispatch('toast', message: 'Recurring invoice saved.');
    }

    /** Raise the next invoice today instead of waiting for its date. */
    public function generateNow(int $id)
    {
        $invoice = RecurringInvoice::with('items')->findOrFail($id)->generate();
        if (! $invoice) {
            $this->dispatch('toast', message: 'Nothing to raise: it is paused, finished or has no lines.', type: 'error');

            return null;
        }
        session()->flash('status', 'Draft invoice '.$invoice->number.' raised. Check it and send it.');

        return $this->redirect(route('invoices.show', $invoice));
    }

    public function toggle(int $id): void
    {
        $template = RecurringInvoice::findOrFail($id);
        $template->update(['active' => ! $template->active]);
        $this->dispatch('toast', message: $template->active ? 'Switched on.' : 'Paused. No invoices are raised until you switch it on again.');
    }

    public function delete(int $id): void
    {
        RecurringInvoice::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Recurring invoice deleted. Invoices it already raised are kept.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function render()
    {
        $templates = RecurringInvoice::with(['client', 'items'])->withCount('invoices')->orderByDesc('active')->orderBy('next_date')->get();
        // What the active templates bring in per month, before tax.
        $monthly = $templates->where('active', true)->sum(fn ($t) => $t->amount() / (RecurringInvoice::MONTHS[$t->frequency] ?? 1));

        return view('livewire.invoices.recurring', [
            'templates' => $templates, 'monthly' => $monthly, 'activeCount' => $templates->where('active', true)->count(),
            'clients' => $this->showForm ? Client::notArchived()->orderBy('name')->get(['id', 'name']) : collect(),
            'formTotal' => collect($this->items)->sum(fn ($i) => (float) ($i['quantity'] ?: 0) * (float) ($i['unit_price'] ?: 0)),
        ]);
    }
}
