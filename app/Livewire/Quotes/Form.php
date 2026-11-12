<?php

namespace App\Livewire\Quotes;

use App\Livewire\Concerns\EditsQuoteContent;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Lead;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Form extends Component
{
    use EditsQuoteContent;

    public ?Quote $quote = null;

    /** client | lead */
    public string $party = 'client';

    public string $client_id = '';

    public string $lead_id = '';

    public string $contact_id = '';

    public array $form = [];

    public string $templateToAdd = '';

    /** Names of the templates combined into this quote, for reference. */
    public array $templateNames = [];

    public function boot(): void
    {
        Gate::authorize('sales.manage');
    }

    public function mount(?Quote $quote = null): void
    {
        if ($quote?->exists) {
            if ($quote->status !== 'draft') {
                session()->flash('status', 'This quote has been issued. Use “Revise” to start a new editable revision.');
                $this->redirectRoute('quotes.show', $quote);
            }
            $this->quote = $quote;
            $this->party = $quote->client_id ? 'client' : 'lead';
            $this->client_id = (string) ($quote->client_id ?? '');
            $this->lead_id = (string) ($quote->lead_id ?? '');
            $this->contact_id = (string) ($quote->contact_id ?? '');
            $this->form = [
                'number' => $quote->number, 'title' => $quote->title, 'currency' => $quote->currency,
                'issue_date' => $quote->issue_date->format('Y-m-d'), 'expiry_date' => $quote->expiry_date?->format('Y-m-d') ?? '',
                'follow_up_date' => $quote->follow_up_date?->format('Y-m-d') ?? '',
                'discount_percent' => (string) (float) $quote->discount_percent, 'tax_rate' => (string) (float) $quote->tax_rate,
                'recipient_name' => (string) $quote->recipient_name, 'recipient_email' => (string) $quote->recipient_email,
                'recipient_company' => (string) $quote->recipient_company, 'recipient_address' => (string) $quote->recipient_address,
                'notes' => (string) $quote->notes,
            ];
            $this->templateNames = array_values(array_filter(array_map('trim', explode(',', (string) $quote->template_names))));
            $this->sections = $quote->sections->map(fn ($s) => ['id' => $s->id, 'title' => $s->title, 'body' => (string) $s->body, 'is_hidden' => $s->is_hidden])->all();
            $this->items = $quote->items->map(fn ($i) => [
                'id' => $i->id, 'name' => $i->name, 'description' => (string) $i->description, 'quantity' => (string) (float) $i->quantity,
                'unit_price' => (string) (float) $i->unit_price, 'billing_frequency' => $i->billing_frequency,
            ])->all();

            return;
        }

        $this->quote = null;
        $today = today_local();
        $this->form = [
            'number' => Quote::nextNumber(), 'title' => '', 'currency' => setting('currency', 'INR'),
            'issue_date' => $today->toDateString(), 'expiry_date' => $today->copy()->addDays(30)->toDateString(),
            'follow_up_date' => $today->copy()->addDays(3)->toDateString(),
            'discount_percent' => '0', 'tax_rate' => (string) setting('invoice_default_tax_rate', 0),
            'recipient_name' => '', 'recipient_email' => '', 'recipient_company' => '', 'recipient_address' => '', 'notes' => '',
        ];

        if ($lead = Lead::find(request()->query('lead'))) {
            $this->party = 'lead';
            $this->lead_id = (string) $lead->id;
            $this->fillRecipientFromLead($lead);
        } elseif ($client = Client::find(request()->query('client'))) {
            $this->client_id = (string) $client->id;
            $this->fillRecipientFromClient($client);
        }
    }

    public function updatedParty(): void
    {
        $this->reset(['client_id', 'lead_id', 'contact_id']);
    }

    public function updatedClientId($value): void
    {
        $this->contact_id = '';
        if ($client = Client::find($value)) {
            $this->fillRecipientFromClient($client);
        }
    }

    public function updatedLeadId($value): void
    {
        if ($lead = Lead::find($value)) {
            $this->fillRecipientFromLead($lead);
        }
    }

    public function updatedContactId($value): void
    {
        if ($contact = ClientContact::where('client_id', $this->client_id ?: 0)->find($value)) {
            $this->form['recipient_name'] = $contact->name;
            $this->form['recipient_email'] = (string) $contact->email;
        }
    }

    protected function fillRecipientFromClient(Client $client): void
    {
        $contact = $client->contacts()->first();
        $this->contact_id = (string) ($contact?->id ?? '');
        $this->form['recipient_name'] = $contact?->name ?? (string) $client->contact_name;
        $this->form['recipient_email'] = (string) ($contact?->email ?? $client->email);
        $this->form['recipient_company'] = $client->name;
        $this->form['recipient_address'] = (string) $client->billing_address;
    }

    protected function fillRecipientFromLead(Lead $lead): void
    {
        $this->form['recipient_name'] = (string) ($lead->contact_name ?: $lead->name);
        $this->form['recipient_email'] = (string) $lead->email;
        $this->form['recipient_company'] = $lead->name;
        $this->form['recipient_address'] = (string) $lead->location;
    }

    /** Copy a template's visible sections and its price list into this quote. Several templates can be combined. */
    public function addTemplate(): void
    {
        $template = QuoteTemplate::active()->with(['sections', 'items'])->find($this->templateToAdd);
        if (! $template) {
            return;
        }

        foreach ($template->sections->where('is_hidden', false) as $section) {
            // When combining templates, same-titled sections are labelled with the template they came from.
            $title = in_array($section->title, array_column($this->sections, 'title'), true) ? "{$section->title} — {$template->name}" : $section->title;
            $this->sections[] = ['id' => null, 'title' => $title, 'body' => (string) $section->body, 'is_hidden' => false];
        }
        foreach ($template->items as $item) {
            $this->items[] = [
                'id' => null, 'name' => $item->name, 'description' => (string) $item->description, 'quantity' => (string) (float) $item->quantity,
                'unit_price' => (string) (float) $item->unit_price, 'billing_frequency' => $item->billing_frequency,
            ];
        }

        if ($this->form['title'] === '') {
            $this->form['title'] = $template->name;
        }
        if (! $this->templateNames && $this->form['issue_date']) {
            $this->form['expiry_date'] = local_date($this->form['issue_date'])->addDays($template->validity_days)->toDateString();
        }
        $this->templateNames[] = $template->name;
        $this->templateToAdd = '';
        $this->dispatch('toast', message: "“{$template->name}” added. Tailor the sections and prices below.");
    }

    public function totals(): array
    {
        return Quote::computeTotals($this->items, (float) ($this->form['discount_percent'] ?: 0), (float) ($this->form['tax_rate'] ?: 0));
    }

    public function save()
    {
        abort_if($this->quote && $this->quote->fresh()->status !== 'draft', 422, 'Only draft quotes can be edited.');

        $this->validate([
            'party' => ['required', Rule::in(['client', 'lead'])],
            'client_id' => [Rule::requiredIf($this->party === 'client'), 'nullable', 'exists:clients,id'],
            'lead_id' => [Rule::requiredIf($this->party === 'lead'), 'nullable', 'exists:leads,id'],
            'contact_id' => ['nullable', Rule::exists('client_contacts', 'id')->where('client_id', $this->client_id ?: 0)],
            'form.number' => ['required', 'string', 'max:40', Rule::unique('quotes', 'number')->ignore($this->quote?->id)],
            'form.title' => ['required', 'string', 'max:255'],
            'form.currency' => ['required', Rule::in(array_keys(SettingsIndex::CURRENCIES))],
            'form.issue_date' => ['required', 'date'],
            'form.expiry_date' => ['nullable', 'date', 'after_or_equal:form.issue_date'],
            'form.follow_up_date' => ['nullable', 'date'],
            'form.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'form.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'form.recipient_name' => ['required', 'string', 'max:255'],
            'form.recipient_email' => ['nullable', 'email', 'max:255'],
            'form.recipient_company' => ['nullable', 'string', 'max:255'],
            'form.recipient_address' => ['nullable', 'string', 'max:1000'],
            'form.notes' => ['nullable', 'string', 'max:20000'],
        ] + $this->contentRules(), [], [
            'client_id' => 'client', 'lead_id' => 'lead', 'contact_id' => 'recipient', 'form.number' => 'quote number', 'form.title' => 'title',
            'form.issue_date' => 'issue date', 'form.expiry_date' => 'expiry date', 'form.follow_up_date' => 'follow-up date',
            'form.discount_percent' => 'discount', 'form.tax_rate' => 'tax rate', 'form.recipient_name' => 'recipient name', 'form.recipient_email' => 'recipient email',
        ] + $this->contentAttributes());

        $quote = DB::transaction(function () {
            $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
            $data['discount_percent'] ??= 0;
            $data['tax_rate'] ??= 0;
            $data += [
                'client_id' => $this->party === 'client' ? $this->client_id : null,
                'lead_id' => $this->party === 'lead' ? $this->lead_id : ($this->quote?->lead_id),
                'contact_id' => $this->party === 'client' && $this->contact_id ? $this->contact_id : null,
                'template_names' => implode(', ', array_unique($this->templateNames)) ?: null,
            ];

            $quote = $this->quote ?? new Quote(['created_by' => auth()->id()]);
            $quote->fill($data)->save();

            // The quote holds its own copy of every section and line, so template edits never reach it.
            $quote->sections()->delete();
            foreach ($this->sections as $order => $section) {
                $quote->sections()->create(['title' => $section['title'], 'body' => $section['body'] ?: null, 'is_hidden' => (bool) $section['is_hidden'], 'sort_order' => $order]);
            }

            // Lines keep their ids across edits so invoices raised from them stay linked.
            $kept = [];
            foreach ($this->items as $order => $item) {
                $attributes = [
                    'name' => $item['name'], 'description' => $item['description'] ?: null, 'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'],
                    'billing_frequency' => $item['billing_frequency'], 'amount' => round((float) $item['quantity'] * (float) $item['unit_price'], 2), 'sort_order' => $order,
                ];
                $existing = ! empty($item['id']) ? $quote->items()->find($item['id']) : null;
                $existing ? $existing->update($attributes) : $existing = $quote->items()->create($attributes);
                $kept[] = $existing->id;
            }
            $quote->items()->whereNotIn('id', $kept)->delete();

            return $quote;
        });

        session()->flash('status', "Draft {$quote->number} saved.");

        return $this->redirectRoute('quotes.show', $quote);
    }

    public function render()
    {
        return view('livewire.quotes.form', [
            'clients' => Client::query()->where(fn ($q) => $q->whereNull('archived_at')->when($this->client_id, fn ($q, $id) => $q->orWhere('id', $id)))->orderBy('name')->get(['id', 'name']),
            'leads' => Lead::query()->where(fn ($q) => $q->whereNull('client_id')->when($this->lead_id, fn ($q, $id) => $q->orWhere('id', $id)))->orderBy('name')->get(['id', 'name', 'stage']),
            'contacts' => $this->client_id ? ClientContact::where('client_id', $this->client_id)->orderByDesc('is_primary')->orderBy('name')->get() : collect(),
            'templates' => QuoteTemplate::active()->orderBy('name')->get(['id', 'name', 'category']),
            'totals' => $this->totals(),
        ])->title($this->quote ? 'Edit '.$this->quote->number : 'New Quote');
    }
}
