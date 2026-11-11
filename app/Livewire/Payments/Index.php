<?php

namespace App\Livewire\Payments;

use App\Models\Client;
use App\Models\Lookup;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentFilters;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Payments')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $client = '';

    #[Url(except: '')]
    public string $method = '';

    #[Url(except: '')]
    public string $receiver = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: false)]
    public bool $unallocated = false;

    public function boot(): void
    {
        Gate::authorize('finance');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /** Current filters, also passed to the PDF export. */
    public function filters(): array
    {
        return [
            'search' => $this->search, 'client' => $this->client, 'method' => $this->method, 'receiver' => $this->receiver,
            'from' => $this->from, 'to' => $this->to, 'unallocated' => $this->unallocated ? '1' : null,
        ];
    }

    public function render()
    {
        $query = PaymentFilters::apply(Payment::query(), $this->filters());

        return view('livewire.payments.index', [
            'payments' => (clone $query)->with(['client', 'method', 'receiver', 'allocations.invoice'])
                ->latest('payment_date')->latest('id')->paginate(25),
            'total' => (float) (clone $query)->sum('amount'),
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'methods' => Lookup::ofType('payment_method')->get(),
            // Every Super Admin, including deactivated ones, so history stays filterable.
            'receivers' => User::superAdmins()->orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }
}
