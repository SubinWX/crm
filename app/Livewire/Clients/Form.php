<?php

namespace App\Livewire\Clients;

use App\Models\Client;
use App\Models\HostingRecord;
use App\Models\Lookup;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\ClientAdded;
use App\Support\Gst;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;

    /** Newly chosen brand logo (temporary upload). */
    public $logo;

    public bool $removeLogo = false;

    public ?Client $client = null;

    public array $form = [
        'name' => '', 'contact_name' => '', 'email' => '', 'phone1' => '', 'phone2' => '', 'website' => '',
        'billing_address' => '', 'location' => '', 'industry_id' => '', 'join_date' => '', 'status' => 'active',
        'account_manager_id' => '', 'notes' => '', 'acquisition_source_id' => '', 'referrer_id' => '',
        'tax_id' => '', 'state_code' => '', 'preferred_channel' => '',
    ];

    /** @var array<int> */
    public array $workTypeIds = [];

    // Optional first hosting record and first payment, only when creating a client.
    public bool $addHosting = false;

    public array $hosting = [
        'domain' => '', 'server_type_id' => '', 'server_provider_id' => '', 'billing_start' => '', 'billing_end' => '',
        'next_payment_date' => '', 'renewal_amount' => '', 'billing_frequency' => 'yearly',
    ];

    public bool $addPayment = false;

    public array $payment = ['payment_method_id' => '', 'amount' => '', 'payment_date' => '', 'received_by' => '', 'reference' => ''];

    public function boot(): void
    {
        // Adding needs clients.create; changing an existing client needs client.update for that client.
        $this->client?->exists
            ? Gate::authorize('client.update', $this->client)
            : Gate::authorize('clients.create');
    }

    public function mount(?Client $client = null): void
    {
        if ($client?->exists) {
            Gate::authorize('client.update', $client);
            $this->client = $client;
            foreach (array_keys($this->form) as $key) {
                $value = $client->{$key};
                $this->form[$key] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) ($value ?? '');
            }
            $this->workTypeIds = $client->workTypes()->pluck('lookups.id')->map(fn ($id) => (int) $id)->all();
        } else {
            $this->client = null;
            $this->form['join_date'] = today_local()->toDateString();
            $this->payment['payment_date'] = today_local()->toDateString();
        }
    }

    protected function rules(): array
    {
        $lookup = fn (string $type) => ['nullable', Rule::exists('lookups', 'id')->where('type', $type)];

        $rules = [
            'form.name' => ['required', 'string', 'max:255'],
            'form.contact_name' => ['nullable', 'string', 'max:255'],
            'form.email' => ['nullable', 'email', 'max:255'],
            'form.phone1' => ['nullable', 'string', 'max:30'],
            'form.phone2' => ['nullable', 'string', 'max:30'],
            'form.website' => ['nullable', 'string', 'max:255'],
            'form.billing_address' => ['nullable', 'string', 'max:2000'],
            'form.location' => ['nullable', 'string', 'max:255'],
            'form.industry_id' => $lookup('industry'),
            'form.join_date' => ['nullable', 'date'],
            'form.status' => ['required', Rule::in(array_keys(Client::STATUSES))],
            'form.account_manager_id' => ['nullable', 'exists:users,id'],
            'form.notes' => ['nullable', 'string', 'max:20000'],
            'form.acquisition_source_id' => $lookup('acquisition_source'),
            'form.referrer_id' => $lookup('referrer'),
            'form.tax_id' => ['nullable', 'string', 'max:40'],
            'form.state_code' => ['nullable', Rule::in(array_keys(Gst::STATES))],
            'form.preferred_channel' => ['nullable', Rule::in(array_keys(Client::CHANNELS))],
            // Raster images only; SVG is rejected because it can carry scripts.
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'workTypeIds' => ['array'],
            'workTypeIds.*' => [Rule::exists('lookups', 'id')->where('type', 'work_type')],
        ];

        if (! $this->client && $this->addHosting) {
            $rules += [
                'hosting.domain' => ['required', 'string', 'max:255'],
                'hosting.server_type_id' => ['required', Rule::exists('lookups', 'id')->where('type', 'server_type')],
                'hosting.server_provider_id' => $lookup('server_provider'),
                'hosting.billing_start' => ['nullable', 'date'],
                'hosting.billing_end' => ['nullable', 'date', 'after_or_equal:hosting.billing_start'],
                'hosting.next_payment_date' => ['nullable', 'date'],
                'hosting.renewal_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
                'hosting.billing_frequency' => ['required', Rule::in(array_keys(HostingRecord::FREQUENCIES))],
            ];
        }

        if (! $this->client && $this->addPayment && Gate::allows('finance')) {
            $rules += [
                'payment.payment_method_id' => ['required', Rule::exists('lookups', 'id')->where('type', 'payment_method')],
                'payment.amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
                'payment.payment_date' => ['required', 'date'],
                'payment.received_by' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_SUPER_ADMIN)->where('is_active', true)],
                'payment.reference' => ['nullable', 'string', 'max:255'],
            ];
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        $names = [];
        foreach (['form', 'hosting', 'payment'] as $group) {
            foreach (array_keys($this->{$group}) as $key) {
                $names["{$group}.{$key}"] = str_replace(['_id', '_'], ['', ' '], $key);
            }
        }

        return $names;
    }

    public function save()
    {
        $this->validate();

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $creating = $this->client === null;

        $client = DB::transaction(function () use ($data, $creating) {
            if ($creating) {
                $client = Client::create($data + ['created_by' => auth()->id()]);
            } else {
                $client = $this->client;
                $client->update($data);
            }
            $client->workTypes()->sync($this->workTypeIds);
            if ($creating && ($client->contact_name || $client->email)) {
                $client->contacts()->create([
                    'name' => $client->contact_name ?: $client->name, 'email' => $client->email, 'phone' => $client->phone1,
                    'role' => 'primary', 'is_primary' => true,
                ]);
            }

            if ($this->logo || $this->removeLogo) {
                if ($client->logo_path) {
                    Storage::disk('local')->delete($client->logo_path);
                }
                $client->update(['logo_path' => $this->logo ? $this->logo->store('logos', 'local') : null]);
            }

            if ($creating && $this->addHosting) {
                $client->hostingRecords()->create(
                    collect($this->hosting)->map(fn ($v) => $v === '' ? null : $v)->all()
                    + ['status' => 'active']
                );
            }

            if ($creating && $this->addPayment && Gate::allows('finance')) {
                Payment::create([
                    'client_id' => $client->id,
                    'payment_method_id' => $this->payment['payment_method_id'],
                    'amount' => $this->payment['amount'],
                    'payment_date' => $this->payment['payment_date'],
                    'received_by' => $this->payment['received_by'],
                    'reference' => $this->payment['reference'] ?: null,
                    'recorded_by' => auth()->id(),
                ]);
            }

            return $client;
        });

        if ($creating) {
            // In-app + email to every active Super Admin (queued).
            Notification::send(User::activeSuperAdmins(), new ClientAdded($client, auth()->user()->name));
        }

        session()->flash('status', $creating ? "Client {$client->name} added. Super Admins have been notified." : 'Client updated.');

        return $this->redirectRoute('clients.show', $client);
    }

    public function render()
    {
        return view('livewire.clients.form', [
            'industries' => Lookup::options('industry', $this->client?->industry_id),
            'workTypes' => Lookup::options('work_type', $this->workTypeIds),
            'sources' => Lookup::options('acquisition_source', $this->client?->acquisition_source_id),
            'referrers' => Lookup::options('referrer', $this->client?->referrer_id),
            'serverTypes' => Lookup::options('server_type'),
            'providers' => Lookup::options('server_provider'),
            'paymentMethods' => Lookup::options('payment_method'),
            'managers' => User::query()->where(fn ($q) => $q->where('is_active', true)
                ->when($this->client?->account_manager_id, fn ($q, $id) => $q->orWhere('id', $id)))->orderBy('name')->get(),
            'superAdmins' => User::activeSuperAdmins(),
        ])->title($this->client ? 'Edit '.$this->client->name : 'New Client');
    }
}
