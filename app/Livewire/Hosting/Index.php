<?php

namespace App\Livewire\Hosting;

use App\Models\Client;
use App\Models\HostingRecord;
use App\Models\Lookup;
use App\Models\ReminderLog;
use App\Services\ReminderService;
use App\Support\WhatsApp;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Two modes: the "Paid Server Clients" list inside Hosting & Renewals (no client) and the hosting block of a
 * client profile (clientId set, shows every hosting record for that client).
 */
#[Title('Paid Server Clients')]
class Index extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $clientId = null;

    // Query-string names are prefixed so they do not collide with the Renewals page this is shown inside.
    #[Url(as: 'hsearch', except: '')]
    public string $search = '';

    /** '' | overdue | week | two_months */
    #[Url(as: 'hdue', except: '')]
    public string $due = '';

    #[Url(as: 'hinactive', except: false)]
    public bool $includeInactive = false;

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public ?int $renewingId = null;

    public array $renewal = [];

    public ?int $expandedId = null;

    public function booted(): void
    {
        // Inside a client profile: must be allowed to open that client. The overview page is team-only.
        $this->clientId
            ? Gate::authorize('client.access', Client::findOrFail($this->clientId))
            : Gate::authorize('workspace');
        Gate::authorize('clients.view');
    }

    public function mount(?int $clientId = null): void
    {
        $this->clientId = $clientId;
        $this->resetForm();
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'due', 'includeInactive'], true)) {
            $this->resetPage();
        }
    }

    /** Add and edit hosting: clients.manage, or a Sub Admin on a client they added. */
    public function canEdit(): bool
    {
        return $this->clientId
            ? Gate::allows('client.update', Client::findOrFail($this->clientId))
            : Gate::allows('clients.manage');
    }

    protected function resetForm(): void
    {
        $this->form = [
            'client_id' => $this->clientId ?? '', 'domain' => '', 'server_type_id' => '', 'server_provider_id' => '',
            'billing_start' => '', 'billing_end' => '', 'next_payment_date' => '', 'renewal_amount' => '',
            'billing_frequency' => 'yearly', 'status' => 'active', 'notes' => '',
        ];
        $this->editingId = null;
    }

    public function create(): void
    {
        abort_unless($this->canEdit(), 403);
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        abort_unless($this->canEdit(), 403);
        $record = $this->findRecord($id);
        $this->resetValidation();
        $this->editingId = $record->id;
        foreach (array_keys($this->form) as $key) {
            $value = $record->{$key};
            $this->form[$key] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) ($value ?? '');
        }
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless($this->canEdit(), 403);
        if ($this->clientId) {
            $this->form['client_id'] = $this->clientId;
        }

        $this->validate([
            'form.client_id' => ['required', 'exists:clients,id'],
            'form.domain' => ['required', 'string', 'max:255'],
            'form.server_type_id' => ['required', Rule::exists('lookups', 'id')->where('type', 'server_type')],
            'form.server_provider_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', 'server_provider')],
            'form.billing_start' => ['nullable', 'date'],
            'form.billing_end' => ['nullable', 'date', 'after_or_equal:form.billing_start'],
            'form.next_payment_date' => ['nullable', 'date'],
            'form.renewal_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'form.billing_frequency' => ['required', Rule::in(array_keys(HostingRecord::FREQUENCIES))],
            'form.status' => ['required', Rule::in(array_keys(HostingRecord::STATUSES))],
            'form.notes' => ['nullable', 'string', 'max:2000'],
        ], [], collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace(['_id', '_'], ['', ' '], $k)])->all());

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['renewal_amount'] ??= 0;

        if ($this->editingId) {
            $this->findRecord($this->editingId)->update($data);
        } else {
            HostingRecord::create($data);
        }

        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Hosting record saved. Reminder dates follow the next payment date.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('clients.manage');
        $this->findRecord($id)->delete();
        $this->dispatch('toast', message: 'Hosting record deleted.');
    }

    /** With the WhatsApp API connected, the row's WhatsApp button sends the reminder itself and records it. */
    public function sendWhatsApp(int $id): void
    {
        $record = HostingRecord::with('client')->findOrFail($id);
        Gate::authorize('client.access', $record->client);
        abort_unless(WhatsApp::enabled() && $record->next_payment_date, 422);

        $log = WhatsApp::queue('manual', 'hosting', $record->client, $record->client->phone1 ?: $record->client->phone2, [
            'client' => $record->client->name, 'item' => $record->domain ?: 'your website', 'date' => fmt_date($record->next_payment_date),
            'amount' => $record->renewal_amount !== null ? money($record->renewal_amount) : '—',
        ], 'hosting', $record->id);
        activity('whatsapp_reminder', 'WhatsApp hosting reminder for '.($record->domain ?: $record->client->name).' '.($log?->status === 'skipped' ? 'not sent: no valid mobile number' : 'queued'), $record);
        $this->dispatch('toast', message: $log?->status === 'skipped' ? 'Not sent: this client has no valid mobile number saved.' : 'WhatsApp reminder queued for '.$record->client->name.'.');
    }

    public function startRenewal(int $id): void
    {
        Gate::authorize('clients.manage');
        $record = $this->findRecord($id);
        $this->resetValidation();
        $this->renewingId = $record->id;
        $this->renewal = $record->renewalDefaults();
    }

    public function saveRenewal(): void
    {
        Gate::authorize('clients.manage');
        $record = $this->findRecord($this->renewingId);

        $this->validate([
            'renewal.paid_on' => ['required', 'date'],
            'renewal.amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'renewal.new_billing_start' => ['nullable', 'date'],
            'renewal.new_billing_end' => ['nullable', 'date', 'after_or_equal:renewal.new_billing_start'],
            'renewal.next_payment_date' => ['required', 'date', 'after:'.($record->next_payment_date?->toDateString() ?? '1970-01-01')],
            'renewal.new_renewal_amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'renewal.notes' => ['nullable', 'string', 'max:1000'],
        ], ['renewal.next_payment_date.after' => 'The next payment date must be after the payment date being renewed.'],
            collect($this->renewal)->keys()->mapWithKeys(fn ($k) => ["renewal.{$k}" => str_replace('_', ' ', $k)])->all());

        $record->recordRenewal(collect($this->renewal)->map(fn ($v) => $v === '' ? null : $v)->all());
        activity('renewed', "Hosting renewal recorded for {$record->domain}; next payment ".fmt_date($this->renewal['next_payment_date']), $record);

        $this->renewingId = null;
        $this->expandedId = $record->id;
        $this->dispatch('toast', message: 'Renewal recorded. Billing period saved to history.');
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

    protected function findRecord(?int $id): HostingRecord
    {
        return HostingRecord::query()
            ->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))
            ->findOrFail($id);
    }

    /** Reminder dates and delivery state for the expanded record. */
    public function reminderStatus(HostingRecord $record): array
    {
        if (! $record->isPaidType() || $record->status !== 'active' || ! $record->next_payment_date) {
            return [];
        }
        $labels = [ReminderService::HOSTING_TWO_MONTHS => '2 months before', ReminderService::HOSTING_SEVEN_DAYS => '7 days before'];
        $rows = [];
        foreach (ReminderService::hostingSchedule($record) as $event => $date) {
            $sent = ReminderLog::where('subject_type', 'hosting')->where('subject_id', $record->id)
                ->where('event', $event)->whereDate('scheduled_date', $date->toDateString())->whereNotNull('sent_at')->exists();
            $rows[] = ['label' => $labels[$event], 'date' => $date, 'sent' => $sent];
        }

        return $rows;
    }

    public function render()
    {
        $today = today_local()->toDateString();

        $query = HostingRecord::query()
            ->with(['client', 'serverType', 'serverProvider'])
            ->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))
            ->when(! $this->clientId, fn ($q) => $q->paid()->whereHas('client', fn ($q) => $q->whereNull('archived_at')))
            ->when(! $this->clientId && ! $this->includeInactive, fn ($q) => $q->where('status', 'active'))
            ->when($this->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('domain', 'like', "%{$s}%")
                ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$s}%"))
                ->orWhereHas('serverProvider', fn ($q) => $q->where('name', 'like', "%{$s}%"))))
            ->when($this->due === 'overdue', fn ($q) => $q->where('status', 'active')->where('next_payment_date', '<', $today))
            ->when($this->due === 'week', fn ($q) => $q->where('status', 'active')
                ->whereBetween('next_payment_date', [$today, today_local()->addDays(7)->toDateString()]))
            ->when($this->due === 'two_months', fn ($q) => $q->where('status', 'active')
                ->whereBetween('next_payment_date', [$today, today_local()->addMonthsNoOverflow(2)->toDateString()]))
            ->orderByRaw('next_payment_date is null')->orderBy('next_payment_date')->orderBy('domain');

        $counts = null;
        if (! $this->clientId) {
            $base = fn () => HostingRecord::query()->paid()->active()->whereHas('client', fn ($q) => $q->whereNull('archived_at'));
            $counts = [
                'overdue' => $base()->where('next_payment_date', '<', $today)->count(),
                'week' => $base()->whereBetween('next_payment_date', [$today, today_local()->addDays(7)->toDateString()])->count(),
                'two_months' => $base()->whereBetween('next_payment_date', [$today, today_local()->addMonthsNoOverflow(2)->toDateString()])->count(),
            ];
        }

        return view('livewire.hosting.index', [
            'records' => $query->paginate($this->clientId ? 50 : 25),
            'counts' => $counts,
            'expanded' => $this->expandedId ? HostingRecord::with(['renewals.recorder', 'serverType'])->find($this->expandedId) : null,
            'renewing' => $this->renewingId ? HostingRecord::with('client')->find($this->renewingId) : null,
            'serverTypes' => Lookup::options('server_type', $this->form['server_type_id'] ?? null),
            'providers' => Lookup::options('server_provider', $this->form['server_provider_id'] ?? null),
            'clients' => $this->showForm && ! $this->clientId ? Client::notArchived()->orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }
}
