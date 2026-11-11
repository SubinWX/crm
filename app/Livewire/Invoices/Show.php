<?php

namespace App\Livewire\Invoices;

use App\Models\Invoice;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    public Invoice $invoice;

    public $copy;

    public string $sentDate = '';

    public function boot(): void
    {
        Gate::authorize('finance');
    }

    public function mount(): void
    {
        $this->sentDate = today_local()->toDateString();
    }

    public function markSent(): void
    {
        $this->validate(['sentDate' => ['required', 'date', 'before_or_equal:'.today_local()->toDateString()]], [], ['sentDate' => 'sent date']);
        abort_unless($this->invoice->status === 'draft', 422);
        abort_if((float) $this->invoice->total <= 0, 422, 'Add line items before sending.');

        // Store the chosen local calendar day as a UTC timestamp.
        $this->invoice->markSent(local_date($this->sentDate)->setTimeFrom(now(crm_tz()))->utc()->toDateTimeString());
        activity('sent', "Invoice {$this->invoice->number} marked as sent", $this->invoice);
        $this->dispatch('toast', message: 'Invoice marked as sent. It now counts towards the client’s pending amount.');
    }

    public function void(): void
    {
        abort_if($this->invoice->status === 'void', 422);
        if ($this->invoice->allocations()->exists()) {
            $this->dispatch('toast', message: 'Remove the payments applied to this invoice before voiding it.', type: 'error');

            return;
        }
        $this->invoice->update(['status' => 'void']);
        $this->dispatch('toast', message: 'Invoice voided.');
    }

    public function deleteDraft()
    {
        abort_unless($this->invoice->status === 'draft', 422);
        if ($this->invoice->uploaded_pdf_path) {
            Storage::disk('local')->delete($this->invoice->uploaded_pdf_path);
        }
        $this->invoice->delete();
        session()->flash('status', 'Draft invoice deleted.');

        return $this->redirectRoute('invoices.index');
    }

    public function saveCopy(): void
    {
        $this->validate(['copy' => ['required', 'file', 'mimes:pdf', 'max:10240']], [], ['copy' => 'PDF']);

        if ($this->invoice->uploaded_pdf_path) {
            Storage::disk('local')->delete($this->invoice->uploaded_pdf_path);
        }
        $this->invoice->update([
            'uploaded_pdf_path' => $this->copy->store('invoices', 'local'),
            'uploaded_pdf_name' => $this->copy->getClientOriginalName(),
        ]);
        $this->reset('copy');
        $this->dispatch('toast', message: 'Sent copy uploaded.');
    }

    public function removeCopy(): void
    {
        if ($this->invoice->uploaded_pdf_path) {
            Storage::disk('local')->delete($this->invoice->uploaded_pdf_path);
            $this->invoice->update(['uploaded_pdf_path' => null, 'uploaded_pdf_name' => null]);
        }
    }

    public function render()
    {
        $this->invoice->load(['client', 'items', 'creator', 'quote', 'allocations.payment.method', 'allocations.payment.receiver']);

        return view('livewire.invoices.show')->title('Invoice '.$this->invoice->number);
    }
}
