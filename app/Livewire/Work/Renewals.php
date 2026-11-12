<?php

namespace App\Livewire\Work;

use App\Models\Client;
use App\Models\HostingRecord;
use App\Models\Renewal;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The single Hosting & Renewals section. As a page it has a combined overview of everything that
 * renews, the Paid Server Clients list (hosting), and domain / maintenance / retainer renewals.
 * Inside a client profile (clientId) it lists that client's non-hosting renewals.
 */
#[Title('Hosting & Renewals')]
class Renewals extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $clientId = null;

    #[Url(except: '')]
    public string $search = '';

    /** '' = everything (overview) | hosting | domain | maintenance | retainer | other */
    #[Url(except: '')]
    public string $type = '';

    /** '' | overdue | soon */
    #[Url(except: '')]
    public string $due = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public ?int $renewingId = null;

    public array $renewal = [];

    public ?int $expandedId = null;

    public function mount(?int $clientId = null): void
    {
        $this->clientId = $clientId;
        $this->resetForm();
    }

    public function booted(): void
    {
        Gate::authorize('workspace');
        $this->clientId ? Gate::authorize('client.access', Client::findOrFail($this->clientId)) : Gate::authorize('clients.view');
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'type', 'due'], true)) {
            $this->resetPage();
        }
    }

    protected function resetForm(): void
    {
        $this->form = [
            'client_id' => (string) ($this->clientId ?? ''), 'type' => array_key_exists($this->type, Renewal::TYPES) ? $this->type : 'domain', 'name' => '', 'provider' => '', 'renewal_date' => '',
            'amount' => '', 'frequency' => 'yearly', 'status' => 'active', 'notes' => '',
        ];
        $this->editingId = null;
    }

    protected function find(?int $id): Renewal
    {
        return Renewal::query()->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))->findOrFail($id);
    }

    public function create(): void
    {
        Gate::authorize('clients.manage');
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('clients.manage');
        $renewal = $this->find($id);
        $this->resetValidation();
        $this->editingId = $renewal->id;
        foreach (array_keys($this->form) as $key) {
            $value = $renewal->{$key};
            $this->form[$key] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) ($value ?? '');
        }
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('clients.manage');
        if ($this->clientId) {
            $this->form['client_id'] = (string) $this->clientId;
        }
        $this->validate([
            'form.client_id' => ['required', 'exists:clients,id'],
            'form.type' => ['required', Rule::in(array_keys(Renewal::TYPES))],
            'form.name' => ['required', 'string', 'max:255'],
            'form.provider' => ['nullable', 'string', 'max:255'],
            'form.renewal_date' => ['nullable', 'date'],
            'form.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'form.frequency' => ['required', Rule::in(array_keys(HostingRecord::FREQUENCIES))],
            'form.status' => ['required', Rule::in(array_keys(Renewal::STATUSES))],
            'form.notes' => ['nullable', 'string', 'max:2000'],
        ], [], collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace(['_id', '_'], ['', ' '], $k)])->all());

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['amount'] ??= 0;
        $this->editingId ? $this->find($this->editingId)->update($data) : Renewal::create($data);

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Renewal saved. Reminders follow the renewal date.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('clients.manage');
        $this->find($id)->delete();
    }

    /** With the WhatsApp API connected, the row's WhatsApp button sends the reminder itself and records it. */
    public function sendWhatsApp(int $id): void
    {
        $renewal = Renewal::with('client')->findOrFail($id);
        Gate::authorize('client.access', $renewal->client);
        abort_unless(WhatsApp::enabled() && $renewal->renewal_date, 422);

        $log = WhatsApp::queue('manual', 'renewal', $renewal->client, $renewal->client->phone1 ?: $renewal->client->phone2, [
            'client' => $renewal->client->name, 'item' => $renewal->name, 'date' => fmt_date($renewal->renewal_date),
            'amount' => $renewal->amount !== null ? money($renewal->amount) : '—',
        ], 'renewal', $renewal->id);
        activity('whatsapp_reminder', "WhatsApp renewal reminder for {$renewal->name} ".($log?->status === 'skipped' ? 'not sent: no valid mobile number' : 'queued'), $renewal);
        $this->dispatch('toast', message: $log?->status === 'skipped' ? 'Not sent: this client has no valid mobile number saved.' : 'WhatsApp reminder queued for '.$renewal->client->name.'.');
    }

    public function startRenewal(int $id): void
    {
        Gate::authorize('clients.manage');
        $renewal = $this->find($id);
        $this->resetValidation();
        $this->renewingId = $renewal->id;
        $this->renewal = [
            'paid_on' => today_local()->toDateString(), 'amount' => (string) (float) $renewal->amount,
            'next_date' => HostingRecord::advance($renewal->renewal_date, $renewal->frequency)?->toDateString() ?? '',
            'new_amount' => (string) (float) $renewal->amount,
        ];
    }

    public function saveRenewal(): void
    {
        Gate::authorize('clients.manage');
        $renewal = $this->find($this->renewingId);
        $this->validate([
            'renewal.paid_on' => ['required', 'date'],
            'renewal.amount' => ['required', 'numeric', 'min:0'],
            'renewal.next_date' => ['required', 'date', 'after:'.($renewal->renewal_date?->toDateString() ?? '1970-01-01')],
            'renewal.new_amount' => ['required', 'numeric', 'min:0'],
        ], ['renewal.next_date.after' => 'The next renewal date must be after the one being renewed.'],
            ['renewal.paid_on' => 'paid on', 'renewal.amount' => 'amount', 'renewal.next_date' => 'next renewal date', 'renewal.new_amount' => 'amount going forward']);

        $renewal->recordRenewal($this->renewal);
        activity('renewed', "{$renewal->name} renewed; next renewal ".fmt_date($this->renewal['next_date']), $renewal);
        $this->renewingId = null;
        $this->expandedId = $renewal->id;
        $this->dispatch('toast', message: 'Renewal recorded and saved to history.');
    }

    public function toggle(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function closeModal(): void
    {
        $this->showForm = false;
        $this->renewingId = null;
    }

    public function render()
    {
        $today = today_local()->toDateString();

        $renewals = Renewal::query()->with('client')
            ->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId), fn ($q) => $q->whereHas('client', fn ($q) => $q->whereNull('archived_at')))
            ->when($this->type && $this->type !== 'hosting', fn ($q, $v) => $q->where('type', $this->type))
            ->when($this->due === 'overdue', fn ($q) => $q->where('status', 'active')->where('renewal_date', '<', $today))
            ->when($this->due === 'soon', fn ($q) => $q->where('status', 'active')->whereBetween('renewal_date', [$today, today_local()->addDays(60)->toDateString()]))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('provider', 'like', "%{$s}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$s}%"))))
            ->orderByRaw("status != 'active'")->orderByRaw('renewal_date is null')->orderBy('renewal_date')->paginate(25);

        // Overview: hosting records and other renewals in one date-ordered list.
        $overview = collect();
        if (! $this->clientId && $this->type === '') {
            $soonUntil = today_local()->addDays(60)->toDateString();
            $match = fn (array $row) => (! $this->search || str_contains(mb_strtolower($row['name'].' '.$row['client'].' '.$row['provider']), mb_strtolower($this->search)))
                && ($this->due !== 'overdue' || ($row['date'] && $row['date']->toDateString() < $today))
                && ($this->due !== 'soon' || ($row['date'] && $row['date']->toDateString() >= $today && $row['date']->toDateString() <= $soonUntil));

            $overview = HostingRecord::query()->paid()->active()->with(['client', 'serverProvider'])->whereHas('client', fn ($q) => $q->whereNull('archived_at'))->get()
                ->map(fn (HostingRecord $h) => ['type' => 'hosting', 'label' => 'Hosting', 'client' => $h->client->name, 'client_id' => $h->client_id, 'name' => $h->domain,
                    'provider' => (string) $h->serverProvider?->name, 'date' => $h->next_payment_date, 'amount' => $h->renewal_amount, 'frequency' => $h->billing_frequency])
                ->concat(Renewal::query()->where('status', 'active')->with('client')->whereHas('client', fn ($q) => $q->whereNull('archived_at'))->get()
                    ->map(fn (Renewal $r) => ['type' => $r->type, 'label' => Renewal::TYPES[$r->type] ?? $r->type, 'client' => $r->client->name, 'client_id' => $r->client_id, 'name' => $r->name,
                        'provider' => (string) $r->provider, 'date' => $r->renewal_date, 'amount' => $r->amount, 'frequency' => $r->frequency]))
                ->filter($match)
                ->sortBy(fn ($row) => $row['date']?->toDateString() ?? '9999-12-31')->values();
        }

        return view('livewire.work.renewals', [
            'overview' => $overview,
            'renewals' => $renewals,
            'expanded' => $this->expandedId ? Renewal::with('logs.recorder')->find($this->expandedId) : null,
            'renewing' => $this->renewingId ? Renewal::with('client')->find($this->renewingId) : null,
            'clients' => $this->showForm && ! $this->clientId ? Client::notArchived()->orderBy('name')->get(['id', 'name']) : collect(),
            'canManage' => Gate::allows('clients.manage'),
        ]);
    }
}
