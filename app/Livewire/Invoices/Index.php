<?php

namespace App\Livewire\Invoices;

use App\Models\Client;
use App\Models\Invoice;
use App\Support\InvoiceFilters;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Invoices')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $client = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function boot(): void
    {
        Gate::authorize('finance');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'client', 'status', 'from', 'to']);
        $this->resetPage();
    }

    /** Current filters, also passed to the PDF export. */
    public function filters(): array
    {
        return ['search' => $this->search, 'client' => $this->client, 'status' => $this->status, 'from' => $this->from, 'to' => $this->to];
    }

    public function render()
    {
        $query = InvoiceFilters::apply(Invoice::query()->with('client'), $this->filters());

        $open = (clone $query)->whereIn('status', Invoice::OPEN_STATUSES);

        return view('livewire.invoices.index', [
            'invoices' => (clone $query)->latest('invoice_date')->latest('id')->paginate(25),
            'summary' => [
                'count' => (clone $query)->count(),
                'outstanding' => (float) (clone $open)->sum('total') - (float) (clone $open)->sum('amount_paid'),
                'overdue' => (clone $query)->overdue()->count(),
            ],
            'clients' => Client::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
