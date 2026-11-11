<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\Lookup;
use App\Models\User;
use App\Support\ClientFilters;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Clients')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $industry = '';

    #[Url(except: '')]
    public string $workType = '';

    #[Url(except: '')]
    public string $source = '';

    #[Url(except: '')]
    public string $manager = '';

    #[Url(except: false)]
    public bool $archived = false;

    public function boot(): void
    {
        Gate::authorize('clients.view');
    }

    public function updated($property): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'industry', 'workType', 'source', 'manager', 'archived']);
        $this->resetPage();
    }

    public function archive(int $id): void
    {
        Gate::authorize('clients.manage');
        $client = Client::findOrFail($id);
        $client->update(['archived_at' => now()]);
        $this->dispatch('toast', message: "{$client->name} archived.");
    }

    public function restore(int $id): void
    {
        Gate::authorize('clients.manage');
        $client = Client::findOrFail($id);
        $client->update(['archived_at' => null]);
        $this->dispatch('toast', message: "{$client->name} restored.");
    }

    /** Current filters, also passed to the CSV export. */
    public function filters(): array
    {
        return [
            'search' => $this->search, 'status' => $this->status, 'industry' => $this->industry, 'workType' => $this->workType,
            'source' => $this->source, 'manager' => $this->manager, 'archived' => $this->archived ? '1' : null,
        ];
    }

    public function render()
    {
        $clients = ClientFilters::apply(
            Client::query()->with(['industry', 'workTypes', 'accountManager', 'acquisitionSource']), $this->filters(), auth()->user()
        )
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.clients.index', [
            'clients' => $clients,
            'industries' => Lookup::ofType('industry')->get(),
            'workTypes' => Lookup::ofType('work_type')->get(),
            'sources' => Lookup::ofType('acquisition_source')->get(),
            'managers' => User::orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }
}
