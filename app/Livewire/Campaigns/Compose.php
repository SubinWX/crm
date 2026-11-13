<?php

namespace App\Livewire\Campaigns;

use App\Jobs\SendCampaignMessage;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Client;
use App\Models\Lookup;
use App\Support\WhatsApp;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Write a bulk message: pick email or WhatsApp, choose which clients get it, write it once with
 * {client} and {contact} filled in for each one, send yourself a test, then send. Super Admins only.
 */
#[Title('New bulk message')]
class Compose extends Component
{
    public string $name = '';

    public string $channel = 'email';

    public string $subject = '';

    public string $body = '';

    public string $whatsappTemplate = '';

    /** Who gets it. */
    public array $statuses = ['active'];

    public string $industry = '';

    public string $workType = '';

    /** '' | 30 | 60 | 90: only clients with a renewal or hosting payment due within that many days. */
    public string $renewalDays = '';

    /** Clients unticked in the list. */
    public array $excluded = [];

    public function booted(): void
    {
        Gate::authorize('admin');
    }

    public function updated($property): void
    {
        if (in_array($property, ['statuses', 'industry', 'workType', 'renewalDays', 'channel'], true)) {
            $this->excluded = [];
        }
    }

    /** Clients matching the filters who have not opted out. */
    protected function audience()
    {
        $days = in_array($this->renewalDays, ['30', '60', '90'], true) ? (int) $this->renewalDays : null;
        $from = today_local()->toDateString();
        $to = $days ? today_local()->addDays($days)->toDateString() : null;

        return Client::query()->notArchived()->whereNull('bulk_opt_out_at')
            ->whereIn('status', array_intersect($this->statuses, array_keys(Client::STATUSES)) ?: ['-'])
            ->when($this->industry, fn ($q, $v) => $q->where('industry_id', $v))
            ->when($this->workType, fn ($q, $v) => $q->whereHas('workTypes', fn ($q) => $q->where('lookups.id', $v)))
            ->when($days, fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('renewals', fn ($q) => $q->whereBetween('renewal_date', [$from, $to]))
                ->orWhereHas('hostingRecords', fn ($q) => $q->whereBetween('next_payment_date', [$from, $to]))))
            ->orderBy('name')->get(['id', 'name', 'contact_name', 'email', 'billing_contact_email', 'phone1', 'phone2', 'status']);
    }

    /** @return array{0: Collection, 1: Collection} [reachable clients with their address, clients with no address] */
    protected function split(): array
    {
        $all = $this->audience()->each(fn ($c) => $c->address = Campaign::addressFor($c, $this->channel));

        return [$all->filter(fn ($c) => $c->address !== null)->values(), $all->filter(fn ($c) => $c->address === null)->values()];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', Rule::in(array_keys(Campaign::CHANNELS))],
            'subject' => [$this->channel === 'email' ? 'required' : 'nullable', 'string', 'max:255'],
            'body' => ['required', 'string', $this->channel === 'email' ? 'max:30000' : 'max:1500'],
            'whatsappTemplate' => [$this->needsTemplate() ? 'required' : 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/'],
            'statuses' => ['array', 'min:1'],
        ];
    }

    protected function labels(): array
    {
        return ['name' => 'name', 'subject' => 'subject', 'body' => 'message', 'whatsappTemplate' => 'template name', 'statuses' => 'client status'];
    }

    /** Meta only delivers messages that use a template approved in WhatsApp Manager. */
    protected function needsTemplate(): bool
    {
        return $this->channel === 'whatsapp' && WhatsApp::provider() === 'meta';
    }

    /** Email only: send the message to yourself first, filled in for the first client in the list. */
    public function sendTest(): void
    {
        abort_unless($this->channel === 'email', 422);
        $this->validate(collect($this->rules())->only(['subject', 'body'])->all(), [], $this->labels());
        if (! Campaign::channelReady('email')) {
            $this->addError('body', 'No email server is connected yet. Set it up in Administration → Email & WhatsApp.');

            return;
        }
        $draft = new Campaign(['channel' => 'email', 'subject' => $this->subject, 'body' => $this->body]);
        try {
            Mail::to(auth()->user()->email)->send(CampaignMail::for($draft, $this->split()[0]->first()));
            $this->dispatch('toast', message: 'Test sent to '.auth()->user()->email.'.');
        } catch (\Throwable $e) {
            $this->addError('body', 'The test could not be sent: '.mb_substr($e->getMessage(), 0, 200));
        }
    }

    public function send()
    {
        if (! Campaign::channelReady($this->channel)) {
            $this->addError('channel', $this->channel === 'email'
                ? 'No email server is connected yet. Set it up in Administration → Email & WhatsApp.'
                : 'WhatsApp is not set up yet. Set it up in Administration → Email & WhatsApp.');

            return;
        }

        $this->validate($this->rules(), ['whatsappTemplate.regex' => 'A template name uses small letters, numbers and underscores only.',
            'whatsappTemplate.required' => 'Enter the name of the template approved in WhatsApp Manager.'], $this->labels());
        $clients = $this->split()[0]->reject(fn ($c) => in_array((string) $c->id, array_map('strval', $this->excluded), true))->values();
        if ($clients->isEmpty()) {
            $this->addError('statuses', 'No clients are selected. Change the filters or tick at least one client.');

            return;
        }

        $campaign = DB::transaction(function () use ($clients) {
            $campaign = Campaign::create([
                'name' => trim($this->name), 'channel' => $this->channel, 'subject' => $this->channel === 'email' ? trim($this->subject) : null,
                'body' => $this->body, 'whatsapp_template' => $this->needsTemplate() ? $this->whatsappTemplate : null,
                'status' => 'sending', 'total' => $clients->count(), 'created_by' => auth()->id(),
            ]);
            $now = now();
            CampaignRecipient::insert($clients->map(fn ($c) => [
                'campaign_id' => $campaign->id, 'client_id' => $c->id, 'name' => $c->name, 'recipient' => $c->address,
                'status' => 'queued', 'created_at' => $now, 'updated_at' => $now,
            ])->all());

            return $campaign;
        });
        activity('campaign_sent', auth()->user()->name.' sent the bulk message “'.$campaign->name.'” to '.$campaign->total.' '.($campaign->total === 1 ? 'client' : 'clients'));
        $campaign->recipients()->pluck('id')->each(fn ($id) => SendCampaignMessage::dispatch($id));

        session()->flash('status', 'Sending “'.$campaign->name.'” to '.$campaign->total.' '.($campaign->total === 1 ? 'client' : 'clients').'.');

        return $this->redirect(route('campaigns.index', ['open' => $campaign->id]));
    }

    public function render()
    {
        [$reachable, $unreachable] = $this->split();
        $selected = $reachable->reject(fn ($c) => in_array((string) $c->id, array_map('strval', $this->excluded), true));

        return view('livewire.campaigns.compose', [
            'reachable' => $reachable, 'unreachable' => $unreachable, 'selectedCount' => $selected->count(),
            'optedOut' => Client::notArchived()->whereNotNull('bulk_opt_out_at')->count(),
            'sample' => $selected->first(),
            'ready' => Campaign::channelReady($this->channel),
            'needsTemplate' => $this->needsTemplate(),
            'industries' => Lookup::ofType('industry')->get(),
            'workTypes' => Lookup::ofType('work_type')->get(),
        ]);
    }
}
