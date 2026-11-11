<?php

namespace App\Livewire\Clients;

use App\Models\ActivityLog;
use App\Models\Client;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;

class Show extends Component
{
    public Client $client;

    #[Url(except: 'overview')]
    public string $tab = 'overview';

    public function booted(): void
    {
        Gate::authorize('client.access', $this->client);
    }

    public function tabs(): array
    {
        $team = Gate::allows('workspace'); // Sub Admins get the client's own details only

        return array_filter([
            'overview' => 'Overview',
            'contacts' => 'Contacts',
            'log' => $team ? 'Contact Log' : null,
            'quotes' => Gate::allows('sales.manage') ? 'Quotes' : null,
            'projects' => $team ? 'Projects' : null,
            'support' => Gate::allows('support.manage') ? 'Support' : null,
            'renewals' => 'Hosting & Renewals',
            'finance' => Gate::allows('finance') ? 'Invoices & Payments' : null,
            'tasks' => $team ? 'Tasks & Follow-ups' : null,
            'files' => 'Files',
            'activity' => $team ? 'Activity' : null,
        ]);
    }

    public function setTab(string $tab): void
    {
        $tab = $tab === 'hosting' ? 'renewals' : $tab; // old links
        $this->tab = array_key_exists($tab, $this->tabs()) ? $tab : 'overview';
    }

    public function toggleArchive(): void
    {
        Gate::authorize('clients.manage');
        $this->client->update(['archived_at' => $this->client->isArchived() ? null : now()]);
        $this->dispatch('toast', message: $this->client->isArchived() ? 'Client archived.' : 'Client restored.');
    }

    public function render()
    {
        if ($this->tab === 'hosting') {
            $this->tab = 'renewals'; // links saved before hosting moved into this tab
        }
        if (! array_key_exists($this->tab, $this->tabs())) {
            $this->tab = 'overview';
        }

        $this->client->load(['industry', 'workTypes', 'accountManager', 'acquisitionSource', 'referrer', 'creator']);

        return view('livewire.clients.show', [
            'tabs' => $this->tabs(),
            'financials' => Gate::allows('finance') ? $this->client->financials() : null,
            'hosting' => $this->tab === 'overview'
                ? $this->client->hostingRecords()->with(['serverType', 'serverProvider'])->orderBy('next_payment_date')->get() : collect(),
            'openTasks' => $this->tab === 'overview' && Gate::allows('workspace') ? $this->client->tasks()->open()->with('assignee')->orderBy('due_date')->limit(5)->get() : collect(),
            'activity' => $this->tab === 'activity'
                ? ActivityLog::with('user')->where('client_id', $this->client->id)
                    // Staff do not see financial entries in the client timeline.
                    ->when(! Gate::allows('finance'), fn ($q) => $q->whereNotIn('subject_type', ['invoice', 'payment', 'expense']))
                    ->latest('created_at')->latest('id')->limit(100)->get()
                : collect(),
        ])->title($this->client->name);
    }
}
