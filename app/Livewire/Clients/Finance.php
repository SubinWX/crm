<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Finance extends Component
{
    public Client $client;

    public function boot(): void
    {
        Gate::authorize('finance');
    }

    public function render()
    {
        return view('livewire.clients.finance', [
            'financials' => $this->client->financials(),
            'invoices' => $this->client->invoices()->latest('invoice_date')->latest('id')->get(),
            'payments' => $this->client->payments()->with(['method', 'receiver', 'allocations.invoice'])
                ->latest('payment_date')->latest('id')->get(),
        ]);
    }
}
