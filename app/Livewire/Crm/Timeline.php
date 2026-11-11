<?php

namespace App\Livewire\Crm;

use App\Models\Client;
use App\Models\Interaction;
use App\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The manual contact log (calls, emails, WhatsApp, meetings, notes) for one lead or one client.
 * After a lead is converted, its entries appear on the client as well. Things to do next are tasks.
 */
class Timeline extends Component
{
    #[Locked]
    public ?int $leadId = null;

    #[Locked]
    public ?int $clientId = null;

    public array $log = [];

    public function mount(?int $leadId = null, ?int $clientId = null): void
    {
        $this->leadId = $leadId;
        $this->clientId = $clientId;
        $this->resetForm();
    }

    public function booted(): void
    {
        // Leads belong to sales; a client's log is open to the team members who can open that client.
        if ($this->leadId) {
            Gate::authorize('sales.manage');
        } else {
            Gate::authorize('workspace');
            Gate::authorize('client.access', Client::findOrFail($this->clientId));
        }
    }

    protected function resetForm(): void
    {
        $this->log = ['type' => 'call', 'summary' => '', 'happened_at' => now(crm_tz())->format('Y-m-d\TH:i')];
    }

    protected function scope($query)
    {
        return $this->leadId ? $query->where('lead_id', $this->leadId) : $query->where('client_id', $this->clientId);
    }

    public function addLog(): void
    {
        $this->validate([
            'log.type' => ['required', Rule::in(array_keys(Interaction::TYPES))],
            'log.summary' => ['required', 'string', 'min:2', 'max:5000'],
            'log.happened_at' => ['required', 'date'],
        ], [], ['log.summary' => 'summary', 'log.happened_at' => 'date and time']);

        Interaction::create([
            'lead_id' => $this->leadId,
            // Lead entries also carry the client id once the lead is converted, so both pages show them.
            'client_id' => $this->leadId ? Lead::whereKey($this->leadId)->value('client_id') : $this->clientId,
            'type' => $this->log['type'], 'summary' => trim($this->log['summary']),
            // Entered in the business time zone, stored in UTC.
            'happened_at' => Carbon::parse($this->log['happened_at'], crm_tz())->utc(),
            'user_id' => auth()->id(),
        ]);

        if ($this->leadId) {
            Lead::whereKey($this->leadId)->where('stage', 'new')->update(['stage' => 'contacted']);
        }
        $this->resetForm();
        $this->dispatch('toast', message: 'Logged.');
    }

    public function deleteLog(int $id): void
    {
        $entry = $this->scope(Interaction::query())->findOrFail($id);
        abort_unless($entry->user_id === auth()->id() || Gate::allows('admin'), 403);
        $entry->delete();
    }

    public function render()
    {
        return view('livewire.crm.timeline', [
            'entries' => $this->scope(Interaction::query())->with('user')->latest('happened_at')->latest('id')->limit(100)->get(),
        ]);
    }
}
