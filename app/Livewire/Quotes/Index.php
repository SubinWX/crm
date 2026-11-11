<?php

namespace App\Livewire\Quotes;

use App\Models\Quote;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** The Quotes & Proposals tab. Also embedded in client and lead profiles (clientId / leadId set). */
#[Title('Quotes & Proposals')]
class Index extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $clientId = null;

    #[Locked]
    public ?int $leadId = null;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public function boot(): void
    {
        Gate::authorize('sales.manage');
    }

    public function mount(?int $clientId = null, ?int $leadId = null): void
    {
        $this->clientId = $clientId;
        $this->leadId = $leadId;
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $quotes = Quote::query()->with(['client', 'lead', 'items'])
            ->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))
            ->when($this->leadId, fn ($q) => $q->where('lead_id', $this->leadId))
            ->when($this->status, fn ($q, $v) => $q->displayStatus($v))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('number', 'like', "%{$s}%")
                ->orWhere('title', 'like', "%{$s}%")->orWhere('recipient_name', 'like', "%{$s}%")->orWhere('recipient_company', 'like', "%{$s}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                ->orWhereHas('lead', fn ($q) => $q->where('name', 'like', "%{$s}%"))))
            ->latest('issue_date')->latest('id')->paginate($this->clientId || $this->leadId ? 50 : 25);

        return view('livewire.quotes.index', [
            'quotes' => $quotes,
            'embedded' => (bool) ($this->clientId || $this->leadId),
            'newQuoteUrl' => route('quotes.create', array_filter(['client' => $this->clientId, 'lead' => $this->leadId])),
        ]);
    }
}
