<?php

namespace App\Livewire\Quotes;

use App\Models\Invoice;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component
{
    public Quote $quote;

    public bool $showInvoiceForm = false;

    /** quote item id => ['selected' => bool, 'amount' => string] */
    public array $bill = [];

    public string $invoiceDate = '';

    public string $invoiceDueDate = '';

    public string $followUpDate = '';

    public function boot(): void
    {
        Gate::authorize('sales.manage');
    }

    public function mount(): void
    {
        $this->followUpDate = $this->quote->follow_up_date?->format('Y-m-d') ?? '';
    }

    public function markSent(): void
    {
        abort_unless($this->quote->status === 'draft', 422);
        if ($this->quote->items()->doesntExist() && $this->quote->sections()->where('is_hidden', false)->doesntExist()) {
            $this->dispatch('toast', message: 'Add at least one section or line item before sending.', type: 'error');

            return;
        }
        $this->quote->markSent();
        activity('sent', "Quote {$this->quote->number} (revision {$this->quote->revision}) marked as sent", $this->quote);
        $this->dispatch('toast', message: 'Marked as sent. The issued PDF is stored and will not change.');
    }

    public function respond(string $status): void
    {
        abort_unless(in_array($status, ['accepted', 'declined'], true) && $this->quote->status === 'sent', 422);
        $this->quote->respond($status);
        activity($status, "Quote {$this->quote->number} {$status}", $this->quote);
        $this->dispatch('toast', message: 'Quote marked as '.$status.'.');
    }

    /** Start a new draft revision. Issued PDFs of earlier revisions stay available. */
    public function revise()
    {
        abort_if($this->quote->status === 'draft', 422);
        $this->quote->revise();
        session()->flash('status', "Revision {$this->quote->revision} started. Earlier issued PDFs are kept.");

        return $this->redirectRoute('quotes.edit', $this->quote);
    }

    public function saveFollowUp(): void
    {
        $this->validate(['followUpDate' => ['nullable', 'date']], [], ['followUpDate' => 'follow-up date']);
        $this->quote->update(['follow_up_date' => $this->followUpDate ?: null]);
        $this->dispatch('toast', message: $this->followUpDate ? 'Follow-up date saved.' : 'Follow-up cleared.');
    }

    public function deleteDraft()
    {
        abort_unless($this->quote->status === 'draft' && $this->quote->revisions()->doesntExist(), 422);
        $this->quote->delete();
        session()->flash('status', 'Draft quote deleted.');

        return $this->redirectRoute('quotes.index');
    }

    /** A quote for a lead needs a client record before it can be invoiced. */
    public function convertLead(): void
    {
        abort_unless($this->quote->lead && ! $this->quote->client_id, 422);
        $lead = $this->quote->lead;
        $client = $lead->convertToClient($lead->matchingClient());
        $this->quote->refresh();
        $this->dispatch('toast', message: "{$lead->name} is now the client “{$client->name}”. Its history came across.");
    }

    public function startInvoice(): void
    {
        Gate::authorize('finance');
        abort_unless($this->quote->status === 'accepted' && $this->quote->client_id, 422);

        $this->bill = [];
        foreach ($this->billableItems() as $row) {
            // One-time lines default to what is still unbilled; recurring lines default to one period.
            $default = $row['recurring'] ? $row['net'] : max($row['remaining'], 0);
            $this->bill[$row['id']] = ['selected' => false, 'amount' => (string) $default];
        }
        $this->invoiceDate = today_local()->toDateString();
        $this->invoiceDueDate = today_local()->addDays((int) setting('invoice_due_days', 15))->toDateString();
        $this->resetValidation();
        $this->showInvoiceForm = true;
    }

    /** Per line: net amount after the quote discount, what is already invoiced, and what is left. */
    public function billableItems(): array
    {
        return $this->quote->items()->get()->map(function ($item) {
            $net = $item->netAmount((float) $this->quote->discount_percent);
            $invoiced = $item->invoicedAmount();

            return [
                'id' => $item->id, 'name' => $item->name, 'frequency' => $item->billing_frequency,
                'recurring' => $item->billing_frequency !== 'one_time', 'net' => $net, 'invoiced' => $invoiced,
                'remaining' => round($net - $invoiced, 2),
            ];
        })->all();
    }

    public function createInvoice()
    {
        Gate::authorize('finance');
        abort_unless($this->quote->status === 'accepted' && $this->quote->client_id, 422);

        $this->validate([
            'invoiceDate' => ['required', 'date'],
            'invoiceDueDate' => ['nullable', 'date', 'after_or_equal:invoiceDate'],
            'bill.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [], ['invoiceDate' => 'invoice date', 'invoiceDueDate' => 'due date', 'bill.*.amount' => 'amount']);

        $rows = collect($this->billableItems())->keyBy('id');
        $chosen = collect($this->bill)->filter(fn ($b, $id) => ! empty($b['selected']) && (float) ($b['amount'] ?: 0) > 0 && $rows->has($id));

        if ($chosen->isEmpty()) {
            $this->addError('bill', 'Select at least one line and enter the amount to invoice.');

            return null;
        }
        foreach ($chosen as $id => $b) {
            $row = $rows[$id];
            // Guard against double billing of one-time work. Recurring lines are billed every period.
            if (! $row['recurring'] && round((float) $b['amount'], 2) > $row['remaining']) {
                $this->addError("bill.{$id}.amount", 'Only '.money($row['remaining'], $this->quote->currency).' of this line is still unbilled.');

                return null;
            }
        }

        $invoice = DB::transaction(function () use ($chosen, $rows) {
            $invoice = Invoice::create([
                'number' => Invoice::nextNumber(),
                'client_id' => $this->quote->client_id,
                'quote_id' => $this->quote->id,
                'quote_revision' => $this->quote->revision,
                'contact_id' => $this->quote->contact_id,
                'recipient_name' => $this->quote->recipient_name,
                'recipient_email' => $this->quote->recipient_email,
                'invoice_date' => $this->invoiceDate,
                'planned_send_date' => $this->invoiceDate,
                'due_date' => $this->invoiceDueDate ?: null,
                'currency' => $this->quote->currency,
                'tax_rate' => $this->quote->tax_rate,
                'notes' => "Quote {$this->quote->number} (revision {$this->quote->revision})",
                'created_by' => auth()->id(),
            ]);

            $order = 0;
            foreach ($chosen as $id => $b) {
                $row = $rows[$id];
                $amount = round((float) $b['amount'], 2);
                $partial = ! $row['recurring'] && $amount < $row['net'];
                $invoice->items()->create([
                    'quote_item_id' => $id,
                    'description' => $row['name'].($row['recurring'] ? ' ('.Quote::FREQUENCIES[$row['frequency']].')' : ($partial ? ' — part payment' : '')),
                    'quantity' => 1, 'unit_price' => $amount, 'amount' => $amount, 'sort_order' => $order++,
                ]);
            }
            $invoice->recalculateTotals();

            return $invoice;
        });

        session()->flash('status', "Draft invoice {$invoice->number} created from {$this->quote->number}. Review it, then mark it as sent.");

        return $this->redirectRoute('invoices.show', $invoice);
    }

    public function closeModal(): void
    {
        $this->showInvoiceForm = false;
    }

    public function render()
    {
        $this->quote->load(['client', 'lead', 'sections', 'items', 'revisions.creator', 'creator']);

        return view('livewire.quotes.show', [
            'totals' => $this->quote->totals(),
            'invoices' => Gate::allows('finance') ? $this->quote->invoices()->latest('id')->get() : collect(),
            'billable' => $this->quote->status === 'accepted' ? $this->billableItems() : [],
        ])->title('Quote '.$this->quote->number);
    }
}
