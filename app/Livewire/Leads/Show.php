<?php

namespace App\Livewire\Leads;

use App\Models\Lead;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component
{
    public Lead $lead;

    public string $lostReason = '';

    public function boot(): void
    {
        Gate::authorize('sales.manage');
    }

    public function setStage(string $stage): void
    {
        abort_unless(array_key_exists($stage, Lead::STAGES), 422);
        abort_if($this->lead->isConverted(), 422, 'This lead is already a client.');
        $this->lead->update(['stage' => $stage, 'lost_reason' => $stage === 'lost' ? ($this->lostReason ?: $this->lead->lost_reason) : null]);
        $this->dispatch('toast', message: 'Stage set to '.Lead::STAGES[$stage].'.');
    }

    /** Convert to a client. If a client with the same email (or name) already exists, the lead is linked to it instead of creating a duplicate. */
    public function convert()
    {
        abort_if($this->lead->isConverted(), 422);
        $existing = $this->lead->matchingClient();
        $client = $this->lead->convertToClient($existing);
        activity('converted', "Lead {$this->lead->name} converted to client {$client->name}", $client);

        session()->flash('status', $existing
            ? "Linked to the existing client {$client->name}. The lead's history came across."
            : "{$client->name} is now a client. Contacts, notes, follow-ups and quotes came across.");

        return Gate::allows('clients.view') ? $this->redirectRoute('clients.show', $client) : $this->redirectRoute('leads.show', $this->lead);
    }

    public function render()
    {
        $this->lead->load(['owner', 'source', 'services', 'client']);

        return view('livewire.leads.show', [
            'match' => $this->lead->isConverted() ? null : $this->lead->matchingClient(),
        ])->title($this->lead->name);
    }
}
