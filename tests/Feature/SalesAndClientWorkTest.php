<?php

namespace Tests\Feature;

use App\Livewire\Clients\Contacts;
use App\Livewire\Crm\Timeline;
use App\Livewire\Data\Import;
use App\Livewire\Invoices\Form as InvoiceForm;
use App\Livewire\Leads\Index as LeadsIndex;
use App\Livewire\Leads\Show as LeadShow;
use App\Livewire\Quotes\Form as QuoteForm;
use App\Livewire\Quotes\Show as QuoteShow;
use App\Livewire\Quotes\TemplateForm;
use App\Livewire\Quotes\Templates;
use App\Livewire\Support\Tickets;
use App\Livewire\Tasks\Index as TasksIndex;
use App\Livewire\Work\Projects;
use App\Livewire\Work\Renewals;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\HostingRecord;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\PortalUser;
use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteTemplate;
use App\Models\Renewal;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Services\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SalesAndClientWorkTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected User $sales;

    protected User $plainStaff;

    protected User $sub;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo('2026-11-11 06:00:00'); // 11:30 IST
        $this->admin = User::factory()->superAdmin()->create(['name' => 'Asha Admin']);
        $this->sales = User::factory()->staff(['sales.manage', 'clients.manage', 'tasks.manage'])->create(['name' => 'Sam Sales']);
        $this->plainStaff = User::factory()->staff(['clients.view'])->create();
        $this->sub = User::factory()->create(['role' => User::ROLE_SUB_ADMIN, 'permissions' => null]);
    }

    protected function makeTemplate(string $name, array $items, array $sections = [['Scope of Work', 'We will build it.', false]]): QuoteTemplate
    {
        $component = Livewire::actingAs($this->admin)->test(TemplateForm::class)
            ->set('name', $name)->set('category', 'Web Development')->set('validity_days', '21')
            ->set('sections', array_map(fn ($s) => ['id' => null, 'title' => $s[0], 'body' => $s[1], 'is_hidden' => $s[2]], $sections))
            ->set('items', array_map(fn ($i) => ['id' => null, 'name' => $i[0], 'description' => '', 'quantity' => '1', 'unit_price' => (string) $i[1], 'billing_frequency' => $i[2]], $items));
        $component->call('save')->assertHasNoErrors()->assertRedirect();

        return QuoteTemplate::where('name', $name)->firstOrFail();
    }

    /** Stage 1: templates, combined quote, reopen, PDF, and template edits never changing an issued PDF. */
    public function test_templates_and_quotes_save_reopen_issue_and_revise(): void
    {
        // A new template starts with clearly labelled starter sections; the seeder ships five starter templates.
        $blank = Livewire::actingAs($this->admin)->test(TemplateForm::class);
        $this->assertSame(QuoteTemplate::STARTER_SECTIONS, array_column($blank->get('sections'), 'title'));
        $this->assertStringContainsString('[Starter text — replace', $blank->get('sections')[7]['body']);
        $this->assertSame(5, QuoteTemplate::count());
        $this->assertStringContainsString('[Starter text', QuoteTemplate::first()->sections()->first()->body);

        $website = $this->makeTemplate('Website build', [['Website design and build', 60000, 'one_time']], [
            ['Scope of Work', "Original scope wording.\nPortfolio: https://example.test/portfolio", false],
            ['Exclusions', 'Hidden internal exclusions', true],
        ]);
        $hosting = $this->makeTemplate('Annual hosting', [['Managed hosting', 6500, 'yearly']], [['Scope of Work', 'Hosting scope.', false]]);
        $care = $this->makeTemplate('Monthly care', [['Maintenance', 3500, 'monthly']], []);

        // Reference attachment (the agency's source proposal) is kept with the template and downloadable by sales staff.
        Livewire::actingAs($this->admin)->test(TemplateForm::class, ['template' => $website])
            ->set('uploads', [UploadedFile::fake()->create('our-proposal.pdf', 50, 'application/pdf')])->call('save')->assertHasNoErrors();
        $file = $website->files()->firstOrFail();
        $this->actingAs($this->sales)->get(route('quotes.file', $file))->assertOk();
        $this->actingAs($this->plainStaff)->get(route('quotes.file', $file))->assertForbidden();

        // Duplicate and archive.
        Livewire::actingAs($this->admin)->test(Templates::class)->call('duplicate', $care->id)->assertRedirect();
        $this->assertDatabaseHas('quote_templates', ['name' => 'Monthly care (copy)']);
        Livewire::actingAs($this->admin)->test(Templates::class)->call('toggleArchive', $care->id);
        $this->assertNotNull($care->fresh()->archived_at);
        Livewire::actingAs($this->admin)->test(Templates::class)->call('toggleArchive', $care->id);

        // Sales staff build one quote from three templates and tailor it.
        $client = Client::create(['name' => 'Acme Retail', 'email' => 'owner@acme.test', 'billing_address' => '12 MG Road']);
        $contact = $client->contacts()->create(['name' => 'Bina Billing', 'email' => 'billing@acme.test', 'role' => 'billing']);

        $form = Livewire::actingAs($this->sales)->test(QuoteForm::class)
            ->set('client_id', (string) $client->id)->set('contact_id', (string) $contact->id)
            ->assertSet('form.recipient_name', 'Bina Billing')->assertSet('form.recipient_email', 'billing@acme.test')
            ->set('templateToAdd', (string) $website->id)->call('addTemplate')
            ->set('templateToAdd', (string) $hosting->id)->call('addTemplate')
            ->set('templateToAdd', (string) $care->id)->call('addTemplate')
            ->assertSet('form.title', 'Website build')
            ->assertSet('form.expiry_date', '2026-12-02')            // issue date + the first template's 21 days
            ->assertCount('items', 3)->assertCount('sections', 2)     // hidden template section is not copied
            ->assertSet('sections.1.title', 'Scope of Work — Annual hosting')
            ->set('items.0.unit_price', '55000')                      // tailored for this customer
            ->set('form.discount_percent', '10')->set('form.tax_rate', '18')->set('form.title', 'Website, hosting and care');
        $totals = $form->instance()->totals();
        $this->assertEquals(['subtotal' => 55000.0, 'discount' => 5500.0, 'tax' => 8910.0, 'total' => 58410.0], $totals['one_time']);
        $this->assertEquals(6903.0, $totals['yearly']['total']);     // 6500 − 10% + 18%
        $this->assertEquals(3717.0, $totals['monthly']['total']);
        $form->call('save')->assertHasNoErrors()->assertRedirect();

        $quote = Quote::firstOrFail();
        $this->assertMatchesRegularExpression('/^QUO-2026-0001$/', $quote->number);
        $this->assertSame(['draft', 1, 'Bina Billing'], [$quote->status, $quote->revision, $quote->recipient_name]);
        $this->assertSame('Website build, Annual hosting, Monthly care', $quote->template_names);

        // Reopen the draft: everything comes back, and it can be changed and saved again.
        Livewire::actingAs($this->sales)->test(QuoteForm::class, ['quote' => $quote])
            ->assertSet('form.title', 'Website, hosting and care')->assertCount('items', 3)->assertSet('items.0.unit_price', '55000')
            ->set('sections.0.body', 'Tailored scope for Acme.')->call('save')->assertHasNoErrors();
        $itemIds = $quote->items()->pluck('id')->all();

        // Draft preview renders live; then the quote is issued and its PDF stored.
        $this->actingAs($this->sales)->get(route('quotes.pdf', ['quote' => $quote, 'inline' => 1]))->assertOk()->assertHeader('content-type', 'application/pdf');
        Livewire::actingAs($this->sales)->test(QuoteShow::class, ['quote' => $quote])->call('markSent')->assertHasNoErrors();
        $quote->refresh();
        $this->assertSame('sent', $quote->status);
        $this->assertNotNull($quote->sent_at);
        $rev1 = $quote->revisions()->where('revision', 1)->firstOrFail();
        Storage::disk('local')->assertExists($rev1->pdf_path);
        $issuedBytes = Storage::disk('local')->get($rev1->pdf_path);
        $this->assertStringStartsWith('%PDF', $issuedBytes);
        $this->assertSame('Tailored scope for Acme.', $rev1->snapshot['sections'][0]['body']);

        // Editing the template afterwards changes nothing on the saved quote or its issued PDF.
        Livewire::actingAs($this->admin)->test(TemplateForm::class, ['template' => $website])
            ->set('sections.0.body', 'COMPLETELY NEW TEMPLATE WORDING')->set('items.0.unit_price', '99999')->call('save')->assertHasNoErrors();
        $this->assertSame('COMPLETELY NEW TEMPLATE WORDING', $website->fresh()->sections()->first()->body);
        $this->assertSame('Tailored scope for Acme.', $quote->sections()->first()->body);
        $this->assertEquals(55000.0, (float) $quote->items()->first()->unit_price);
        $this->assertSame($issuedBytes, Storage::disk('local')->get($rev1->pdf_path));
        $this->assertSame($issuedBytes, $this->actingAs($this->sales)->get(route('quotes.pdf', $quote))->getContent());

        // An issued quote cannot be edited until it is revised; revising keeps the earlier version.
        Livewire::actingAs($this->sales)->test(QuoteForm::class, ['quote' => $quote])->assertRedirect(route('quotes.show', $quote));
        Livewire::actingAs($this->sales)->test(QuoteShow::class, ['quote' => $quote])->call('revise')->assertRedirect();
        $this->assertSame(['draft', 2], [$quote->fresh()->status, $quote->fresh()->revision]);
        Livewire::actingAs($this->sales)->test(QuoteForm::class, ['quote' => $quote->fresh()])->set('items.0.unit_price', '50000')->call('save')->assertHasNoErrors();
        $this->assertSame($itemIds, $quote->items()->pluck('id')->all()); // line ids survive edits
        Livewire::actingAs($this->sales)->test(QuoteShow::class, ['quote' => $quote->fresh()])->call('markSent');
        $this->assertSame(2, $quote->revisions()->count());
        $this->assertSame($issuedBytes, $this->actingAs($this->sales)->get(route('quotes.revision', [$quote, 1]))->streamedContent());
        $this->assertEquals(55000.0, $quote->revisions()->where('revision', 1)->first()->snapshot['items'][0]['unit_price']);
        $this->assertEquals(50000.0, $quote->revisions()->where('revision', 2)->first()->snapshot['items'][0]['unit_price']);

        // Status flow: expired is derived; declined and accepted are recorded.
        $this->travelTo('2026-12-20 06:00:00');
        $this->assertSame('expired', $quote->fresh()->display_status);
        $this->assertSame(1, Quote::displayStatus('expired')->count());
        $this->assertSame(0, Quote::awaitingResponse()->count());
        $this->travelTo('2026-11-12 06:00:00');
        $this->assertSame(1, Quote::awaitingResponse()->count());
        $this->actingAs($this->sales)->get(route('dashboard'))->assertOk()->assertSee('Quotes awaiting a response')->assertSee($quote->number);
        Livewire::actingAs($this->sales)->test(QuoteShow::class, ['quote' => $quote->fresh()])->call('respond', 'accepted');
        $this->assertSame('accepted', $quote->fresh()->status);

        // The quote is listed on the Quotes tab and on the client profile; roles are enforced everywhere.
        $this->actingAs($this->sales)->get(route('quotes.index'))->assertOk()->assertSee($quote->number);
        $this->actingAs($this->sales)->get(route('clients.show', ['client' => $client, 'tab' => 'quotes']))->assertOk()->assertSee($quote->number);
        foreach ([$this->plainStaff, $this->sub] as $user) {
            foreach (['quotes.index', 'quotes.create', 'leads.index'] as $route) {
                $this->actingAs($user)->get(route($route))->assertForbidden();
            }
            $this->actingAs($user)->get(route('quotes.show', $quote))->assertForbidden();
            $this->actingAs($user)->get(route('quotes.pdf', $quote))->assertForbidden();
            $this->actingAs($user)->get(route('quotes.revision', [$quote, 1]))->assertForbidden();
            Livewire::actingAs($user)->test(QuoteShow::class, ['quote' => $quote])->assertForbidden();
        }
        $this->actingAs($this->sales)->get(route('quote-templates.index'))->assertForbidden();   // templates are Super Admin only
        Livewire::actingAs($this->sales)->test(TemplateForm::class)->assertForbidden();
        $this->actingAs($this->admin)->get(route('quote-templates.index'))->assertOk()->assertSee('Website build');
    }

    /** Stage 2: leads, follow-ups, conversion keeping history, and invoices linked to the right quote revision. */
    public function test_lead_to_client_to_invoice_keeps_history_and_links_the_quote_revision(): void
    {
        $source = Lookup::where('type', 'acquisition_source')->where('name', 'Referral')->value('id');
        $service = Lookup::where('type', 'work_type')->where('name', 'SEO')->value('id');

        Livewire::actingAs($this->sales)->test(LeadsIndex::class)->call('create')
            ->set('form.name', 'Bluepeak Realty')->set('form.contact_name', 'Vikram Shah')->set('form.email', 'vikram@bluepeak.test')
            ->set('form.phone', '+91 98200 55555')->set('form.acquisition_source_id', (string) $source)->set('form.estimated_value', '120000')
            ->set('serviceIds', [$service])->set('form.next_action', 'Call to discuss scope')->set('form.next_action_date', '2026-11-11')
            ->set('form.notes', 'Wants launch before March.')->call('save')->assertHasNoErrors();
        $lead = Lead::firstOrFail();
        $this->assertSame(['new', $this->sales->id], [$lead->stage, $lead->owner_id]);

        // Log a call (moves New → Contacted) and plan a follow-up. Follow-ups are tasks, and tasks can belong to a lead.
        Livewire::actingAs($this->sales)->test(Timeline::class, ['leadId' => $lead->id])
            ->set('log.type', 'whatsapp')->set('log.summary', 'Shared portfolio on WhatsApp')->call('addLog')->assertHasNoErrors();
        $this->assertSame('contacted', $lead->fresh()->stage);
        Livewire::actingAs($this->sales)->test(TasksIndex::class, ['leadId' => $lead->id])->call('create')
            ->set('form.title', 'Send proposal')->set('form.due_date', '2026-11-10')->call('save')->assertHasNoErrors();
        $task = Task::firstOrFail();
        $this->assertSame([$lead->id, null, $this->sales->id, 'todo'], [$task->lead_id, $task->client_id, $task->assignee_id, $task->status]);

        // One Tasks & Follow-ups list: the overdue task, plus the lead's next action due today for sales staff.
        Livewire::actingAs($this->sales)->test(TasksIndex::class)->set('status', 'due')->set('assignee', 'me')
            ->assertSee('Send proposal')->assertSee('Bluepeak Realty')->assertSee('Sales follow-ups due')->assertSee('Call to discuss scope');
        Livewire::actingAs($this->admin)->test(TasksIndex::class)->set('assignee', 'me')->assertDontSee('Send proposal')->assertDontSee('Call to discuss scope')
            ->set('assignee', '')->assertSee('Send proposal')->assertSee('Call to discuss scope');
        // Staff without the sales permission see the task but not the sales reminders, and cannot open the lead's own list.
        Livewire::actingAs($this->plainStaff)->test(TasksIndex::class)->assertSee('Send proposal')->assertDontSee('Call to discuss scope');
        Livewire::actingAs($this->plainStaff)->test(TasksIndex::class, ['leadId' => $lead->id])->assertForbidden();
        Livewire::actingAs($this->sub)->test(TasksIndex::class)->assertForbidden();
        $this->assertFalse(Schema::hasTable('follow_ups'));
        $this->assertFalse(Route::has('followups.index'));

        // Quote for the lead: sending it moves the lead to Proposal Sent.
        Livewire::actingAs($this->sales)->test(QuoteForm::class)
            ->set('party', 'lead')->set('lead_id', (string) $lead->id)->assertSet('form.recipient_name', 'Vikram Shah')
            ->set('form.title', 'SEO and website')->set('form.discount_percent', '10')->set('form.tax_rate', '18')
            ->set('items', [
                ['id' => null, 'name' => 'Website build', 'description' => '', 'quantity' => '1', 'unit_price' => '100000', 'billing_frequency' => 'one_time'],
                ['id' => null, 'name' => 'Monthly SEO', 'description' => '', 'quantity' => '1', 'unit_price' => '12000', 'billing_frequency' => 'monthly'],
            ])->call('save')->assertHasNoErrors();
        $quote = Quote::firstOrFail();
        $this->assertSame([$lead->id, null], [$quote->lead_id, $quote->client_id]);
        Livewire::actingAs($this->sales)->test(QuoteShow::class, ['quote' => $quote])->call('markSent')->call('respond', 'accepted');
        $this->assertSame('proposal_sent', $lead->fresh()->stage);

        // A quote for a lead cannot be invoiced until the lead is a client.
        Livewire::actingAs($this->admin)->test(QuoteShow::class, ['quote' => $quote->fresh()])->call('startInvoice')->assertStatus(422);
        $this->actingAs($this->sales)->get(route('quotes.show', $quote))->assertOk()->assertSee('Convert lead to client');

        Livewire::actingAs($this->sales)->test(QuoteShow::class, ['quote' => $quote->fresh()])->call('convertLead');
        $lead->refresh();
        $client = Client::where('name', 'Bluepeak Realty')->firstOrFail();

        // Nothing was retyped and the history came across.
        $this->assertSame(['won', $client->id], [$lead->stage, $lead->client_id]);
        $this->assertSame(['Vikram Shah', 'vikram@bluepeak.test', '+91 98200 55555', $source, 'Wants launch before March.', $this->sales->id],
            [$client->contact_name, $client->email, $client->phone1, $client->acquisition_source_id, $client->notes, $client->account_manager_id]);
        $this->assertSame(['SEO'], $client->workTypes()->pluck('name')->all());
        $this->assertTrue($client->contacts()->where('name', 'Vikram Shah')->where('is_primary', true)->exists());
        $this->assertSame(1, $client->interactions()->count());
        $this->assertSame(1, $client->tasks()->where('title', 'Send proposal')->count());   // the lead's task now shows on the client
        $this->assertSame($client->id, $quote->fresh()->client_id);
        $this->assertTrue(ActivityLog::where('subject_type', 'lead')->where('subject_id', $lead->id)->where('client_id', $client->id)->exists());
        Livewire::actingAs($this->sales)->test(Timeline::class, ['clientId' => $client->id])->assertSee('Shared portfolio on WhatsApp');
        Livewire::actingAs($this->sales)->test(TasksIndex::class, ['clientId' => $client->id])->assertSee('Send proposal');
        $this->actingAs($this->sales)->get(route('clients.show', ['client' => $client, 'tab' => 'quotes']))->assertSee($quote->number);
        $this->actingAs($this->sales)->get(route('leads.show', $lead))->assertOk()->assertSee('Converted to the client');

        // A second lead with the same email links to the existing client instead of duplicating it.
        $again = Lead::create(['name' => 'Bluepeak (new enquiry)', 'email' => 'vikram@bluepeak.test']);
        Livewire::actingAs($this->sales)->test(LeadShow::class, ['lead' => $again])->call('convert');
        $this->assertSame($client->id, $again->fresh()->client_id);
        $this->assertSame(1, Client::where('email', 'vikram@bluepeak.test')->count());

        // Revise once so the invoice must point at revision 2, then invoice a 40% deposit plus the first month of SEO.
        Livewire::actingAs($this->sales)->test(QuoteShow::class, ['quote' => $quote->fresh()])->call('revise');
        Livewire::actingAs($this->sales)->test(QuoteShow::class, ['quote' => $quote->fresh()])->call('markSent')->call('respond', 'accepted');
        [$build, $seo] = $quote->items()->get()->all();

        Livewire::actingAs($this->sales)->test(QuoteShow::class, ['quote' => $quote->fresh()])->call('startInvoice')->assertForbidden(); // sales staff cannot raise invoices
        Livewire::actingAs($this->admin)->test(QuoteShow::class, ['quote' => $quote->fresh()])
            ->call('startInvoice')
            ->assertSet("bill.{$build->id}.amount", '90000')          // 100000 less the 10% quote discount
            ->assertSet("bill.{$seo->id}.amount", '10800')
            ->set("bill.{$build->id}.selected", true)->set("bill.{$build->id}.amount", '36000')
            ->set("bill.{$seo->id}.selected", true)
            ->call('createInvoice')->assertHasNoErrors()->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame(['draft', $client->id, $quote->id, 2, 'Vikram Shah'], [$invoice->status, $invoice->client_id, $invoice->quote_id, $invoice->quote_revision, $invoice->recipient_name]);
        $this->assertEquals([46800.0, 8424.0, 55224.0], [(float) $invoice->subtotal, (float) $invoice->tax_amount, (float) $invoice->total]);
        $this->assertSame([$build->id, $seo->id], $invoice->items()->pluck('quote_item_id')->all());
        $this->actingAs($this->admin)->get(route('invoices.show', $invoice))->assertOk()->assertSee('Raised from quote')->assertSee($quote->number);

        // Amounts already invoiced are shown, and one-time work cannot be billed twice over.
        $rows = collect(Livewire::actingAs($this->admin)->test(QuoteShow::class, ['quote' => $quote->fresh()])->instance()->billableItems())->keyBy('id');
        $this->assertEquals([36000.0, 54000.0], [$rows[$build->id]['invoiced'], $rows[$build->id]['remaining']]);
        Livewire::actingAs($this->admin)->test(QuoteShow::class, ['quote' => $quote->fresh()])->call('startInvoice')
            ->set("bill.{$build->id}.selected", true)->set("bill.{$build->id}.amount", '60000')->call('createInvoice')->assertHasErrors("bill.{$build->id}.amount");
        Livewire::actingAs($this->admin)->test(QuoteShow::class, ['quote' => $quote->fresh()])->call('startInvoice')
            ->assertSet("bill.{$build->id}.amount", '54000')->set("bill.{$build->id}.selected", true)->call('createInvoice')->assertHasNoErrors();
        $this->assertSame(2, $quote->invoices()->count());
        // A void invoice frees its amount again.
        $invoice->update(['status' => 'void']);
        $this->assertEquals(54000.0, $build->fresh()->invoicedAmount());
    }

    /** Stage 3: contacts, projects, requests, renewals with configurable reminders, and CSV tools. */
    public function test_contacts_projects_support_renewals_and_csv_tools(): void
    {
        $client = Client::create(['name' => 'Lotus Dental', 'email' => 'hello@lotus.test', 'contact_name' => 'Old Name']);
        $other = Client::create(['name' => 'Other Co']);

        // Contacts: one primary; the client's own contact fields follow it.
        Livewire::actingAs($this->sales)->test(Contacts::class, ['client' => $client])->call('create')
            ->set('form.name', 'Dr Anita Rao')->set('form.email', 'anita@lotus.test')->set('form.role', 'approvals')->set('form.is_primary', true)->call('save')->assertHasNoErrors()
            ->call('create')->set('form.name', 'Accounts Desk')->set('form.email', 'accounts@lotus.test')->set('form.role', 'billing')->call('save')->assertHasNoErrors();
        $this->assertSame('Dr Anita Rao', $client->fresh()->contact_name);
        $billing = ClientContact::where('name', 'Accounts Desk')->firstOrFail();
        Livewire::actingAs($this->plainStaff)->test(Contacts::class, ['client' => $client])->assertOk()->call('create')->assertForbidden();

        // Invoice recipient is chosen from the client's contacts (and only theirs).
        $foreign = $other->contacts()->create(['name' => 'Someone Else']);
        Livewire::actingAs($this->admin)->test(InvoiceForm::class)->set('form.client_id', (string) $client->id)->set('form.contact_id', (string) $foreign->id)
            ->set('items', [['description' => 'Work', 'quantity' => '1', 'unit_price' => '1000']])->call('save')->assertHasErrors('form.contact_id');
        Livewire::actingAs($this->admin)->test(InvoiceForm::class)->set('form.client_id', (string) $client->id)->set('form.contact_id', (string) $billing->id)
            ->set('items', [['description' => 'Work', 'quantity' => '1', 'unit_price' => '1000']])->call('save')->assertHasNoErrors();
        $this->assertSame(['Accounts Desk', 'accounts@lotus.test'], [Invoice::first()->recipient_name, Invoice::first()->recipient_email]);

        // Projects, linked to an accepted quote of the same client.
        $quote = Quote::create(['number' => 'QUO-T-1', 'client_id' => $client->id, 'title' => 'Site', 'issue_date' => '2026-11-01', 'status' => 'accepted', 'recipient_name' => 'A']);
        $draft = Quote::create(['number' => 'QUO-T-2', 'client_id' => $client->id, 'title' => 'Draft', 'issue_date' => '2026-11-01', 'recipient_name' => 'A']);
        Livewire::actingAs($this->sales)->test(Projects::class, ['clientId' => $client->id])->call('create')
            ->set('form.name', 'Website redesign')->set('form.deadline', '2026-11-05')->set('form.next_milestone', 'Design sign-off')
            ->set('form.quote_id', (string) $draft->id)->call('save')->assertHasErrors('form.quote_id')
            ->set('form.quote_id', (string) $quote->id)->set('form.status', 'active')->call('save')->assertHasNoErrors();
        $project = Project::firstOrFail();
        $this->assertSame([$client->id, $quote->id, $this->sales->id], [$project->client_id, $project->quote_id, $project->owner_id]);
        $this->assertTrue($project->isOverdue());
        $this->actingAs($this->plainStaff)->get(route('projects.index'))->assertOk()->assertSee('Website redesign');
        Livewire::actingAs($this->plainStaff)->test(Projects::class)->call('create')->assertForbidden();

        // Bugs, content changes and support requests are support tickets: the team logs one for the customer.
        $agent = User::factory()->staff(['support.manage', 'clients.view'])->create();
        $customer = PortalUser::create(['name' => 'Dr Anita Rao', 'email' => 'anita@lotus.test', 'password' => 'portal-pass-1', 'client_id' => $client->id, 'email_verified_at' => now()]);
        Livewire::actingAs($agent)->test(Tickets::class, ['clientId' => $client->id])->call('create')
            ->set('form.portal_user_id', (string) $customer->id)->set('form.type', 'support')->set('form.priority', 'urgent')
            ->set('form.subject', 'Booking form error')->set('form.description', 'Reported by phone: the booking form shows an error.')
            ->call('save')->assertHasNoErrors()->assertRedirect();
        $ticket = $client->supportTickets()->firstOrFail();
        $this->assertSame(['urgent', $agent->id, $customer->id], [$ticket->priority, $ticket->assigned_to, $ticket->portal_user_id]);
        $this->assertMatchesRegularExpression('/^SUP-\d{4}-0001$/', $ticket->number);
        $this->actingAs($agent)->get(route('clients.show', ['client' => $client, 'tab' => 'support']))->assertOk()->assertSee('Booking form error');
        $this->actingAs($customer, 'portal')->get(route('portal.tickets.show', $ticket->number))->assertOk()->assertSee('Booking form error')->assertDontSee('on behalf of');
        auth('portal')->logout();
        Livewire::actingAs($this->plainStaff, 'web')->test(Tickets::class, ['clientId' => $client->id])->assertForbidden();
        $this->assertFalse(Schema::hasTable('client_requests'));
        $this->assertFalse(Route::has('requests.index'));

        // Renewals reuse the reminder engine; the schedule is configurable per type.
        Livewire::actingAs($this->sales)->test(Renewals::class, ['clientId' => $client->id])->call('create')
            ->set('form.type', 'domain')->set('form.name', 'lotusdental.in')->set('form.renewal_date', '2027-01-10')->set('form.amount', '1200')->call('save')->assertHasNoErrors();
        $renewal = Renewal::firstOrFail();
        $count = fn () => DB::table('notifications')->where('data->category', 'renewal')->count();
        $reminders = app(ReminderService::class);

        $reminders->run();                                   // exactly 60 days before 10 Jan 2027
        $this->assertSame(1, $count());                      // one active Super Admin
        $reminders->run();
        $this->assertSame(1, $count());                      // rerun: no duplicate
        $this->assertStringContainsString('lotusdental.in', DB::table('notifications')->where('data->category', 'renewal')->value('data'));
        $this->travelTo('2027-01-03 06:00:00');
        $reminders->run();
        $this->assertSame(2, $count());                      // the 7-day notice is separate

        // Change the schedule for retainers only: 45 and 10 days.
        Setting::put('renewal_reminder_days_retainer', '45, 10');
        $this->assertSame([45, 10], Renewal::reminderDays('retainer'));
        $this->assertSame([60, 7], Renewal::reminderDays('domain'));
        $retainer = Renewal::create(['client_id' => $client->id, 'type' => 'retainer', 'name' => 'SEO retainer', 'renewal_date' => '2027-02-17', 'amount' => 12000, 'frequency' => 'monthly']);
        $reminders->run();                                   // 3 Jan 2027 is 45 days before 17 Feb
        $this->assertSame(3, $count());
        $this->assertDatabaseHas('reminder_logs', ['event' => 'renewal_45_days', 'subject_type' => 'renewal', 'subject_id' => $retainer->id]);

        // Cancelled renewals are silent; recording a renewal rolls the date and keeps history.
        $retainer->update(['renewal_date' => '2027-01-08', 'status' => 'cancelled']);
        $reminders->run();
        $this->assertSame(3, $count());
        Livewire::actingAs($this->sales)->test(Renewals::class, ['clientId' => $client->id])
            ->call('startRenewal', $renewal->id)->assertSet('renewal.next_date', '2028-01-10')->call('saveRenewal')->assertHasNoErrors();
        $this->assertSame('2028-01-10', $renewal->fresh()->renewal_date->toDateString());
        $this->assertSame('2027-01-10', $renewal->logs()->first()->due_date->toDateString());
        $this->actingAs($this->admin)->get(route('settings.index'))->assertOk()->assertSee('Renewal reminders');

        // Hosting and renewals are one section: an overview of everything that renews, with hosting as one of its tabs.
        $paid = Lookup::where('type', 'server_type')->where('name', 'Paid')->value('id');
        HostingRecord::create(['client_id' => $client->id, 'domain' => 'lotus-hosting.in', 'server_type_id' => $paid, 'next_payment_date' => '2027-01-20', 'renewal_amount' => 6500]);
        $this->actingAs($this->plainStaff)->get(route('renewals.index'))->assertOk()
            ->assertSee('Hosting &amp; Renewals', false)->assertSee('lotus-hosting.in')->assertSee('lotusdental.in')->assertSee('Paid Server Clients (Hosting)');
        $this->actingAs($this->plainStaff)->get(route('renewals.index', ['type' => 'hosting']))->assertOk()->assertSee('lotus-hosting.in')->assertSee('Due within 7 days')->assertDontSee('lotusdental.in');
        $this->actingAs($this->plainStaff)->get(route('renewals.index', ['type' => 'domain']))->assertOk()->assertSee('lotusdental.in')->assertDontSee('lotus-hosting.in');
        $this->actingAs($this->plainStaff)->get('/hosting')->assertRedirect('/renewals?type=hosting');                    // the old address still works
        $this->actingAs($this->admin)->get(route('clients.show', ['client' => $client, 'tab' => 'renewals']))->assertOk()->assertSee('lotus-hosting.in')->assertSee('lotusdental.in');
        $this->actingAs($this->admin)->get(route('clients.show', ['client' => $client, 'tab' => 'hosting']))->assertOk()->assertSee('lotus-hosting.in');   // old links land on the merged tab

        // CSV import: column matching, preview with duplicate and invalid checks, then import.
        $this->travelBack(); // file uploads are time-limited, so use the real clock from here
        $csv = "Company,Contact Name,E-mail,Mobile,City,Lead Source,Budget\n"
            ."Kite Logistics,Rohan Desai,rohan@kite.test,+91 90000 11111,Ahmedabad,Referral,\"1,50,000\"\n"
            ."Maple Interiors,Divya Menon,divya@maple.test,,Hyderabad,Unknown Source,45000\n"
            ."Kite Again,Rohan,rohan@kite.test,,,,\n"                    // repeated in the file
            .",No Name,x@y.test,,,,\n"                                    // invalid: no name
            ."Bad Email Co,Zed,not-an-email,,,,\n";                       // invalid email
        Lead::create(['name' => 'Existing Lead', 'email' => 'divya@maple.test']);   // already in the CRM

        $import = Livewire::actingAs($this->sales)->test(Import::class)
            ->set('file', UploadedFile::fake()->createWithContent('leads.csv', $csv))->call('upload')->assertHasNoErrors()
            ->assertSet('step', 2)->assertSet('mapping.name', '0')->assertSet('mapping.email', '2')->assertSet('mapping.phone', '3')->assertSet('mapping.estimated_value', '6')
            ->call('preview')->assertSet('step', 3);
        $this->assertSame(['new' => 1, 'duplicate' => 2, 'invalid' => 2, 'total' => 5], $import->viewData('counts'));
        $import->assertSee('Email already in the CRM')->assertSee('Repeated in this file')->call('import')->assertSet('step', 4)->assertSet('result.imported', 1)->assertSet('result.skipped', 4);
        $kite = Lead::where('name', 'Kite Logistics')->firstOrFail();
        $this->assertSame(['rohan@kite.test', 'Ahmedabad', '150000.00', $this->sales->id], [$kite->email, $kite->location, $kite->estimated_value, $kite->owner_id]);
        $this->assertNotNull($kite->acquisition_source_id);
        $this->assertSame(2, Lead::count());

        // Clients import needs clients.manage; exports respect filters and permissions.
        Livewire::actingAs($this->plainStaff)->withQueryParams(['type' => 'clients'])->test(Import::class)->assertForbidden();
        Livewire::actingAs($this->sales)->withQueryParams(['type' => 'clients'])->test(Import::class)
            ->set('file', UploadedFile::fake()->createWithContent('clients.csv', "Name,Email,GSTIN\nNew Client Ltd,new@client.test,27ABCDE1234F1Z5\nLotus Dental,hello@lotus.test,\n"))
            ->call('upload')->call('preview')->call('import')->assertSet('result.imported', 1)->assertSet('result.skipped', 1);
        $this->assertSame('27ABCDE1234F1Z5', Client::where('name', 'New Client Ltd')->value('tax_id'));

        $export = $this->actingAs($this->sales)->get(route('clients.export', ['search' => 'Lotus']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Lotus Dental', $export);
        $this->assertStringNotContainsString('Other Co', $export);
        $this->assertStringContainsString('Kite Logistics', $this->actingAs($this->sales)->get(route('leads.export'))->streamedContent());
        $this->actingAs($this->plainStaff)->get(route('clients.export'))->assertForbidden();
        $this->actingAs($this->plainStaff)->get(route('leads.export'))->assertForbidden();
        $this->actingAs($this->sub)->get(route('clients.export'))->assertForbidden();
        foreach (['projects.index', 'renewals.index', 'tasks.index', 'import'] as $route) {
            $this->actingAs($this->sub)->get(route($route))->assertForbidden();
        }
        // Sub Admins still see only their own client's basics; team-only tabs are refused even by direct call.
        $own = Client::create(['name' => 'Sub Own', 'created_by' => $this->sub->id]);
        Renewal::create(['client_id' => $own->id, 'type' => 'domain', 'name' => 'hidden-domain.test', 'renewal_date' => '2027-06-01']);
        $this->actingAs($this->sub)->get(route('clients.show', $own))->assertOk()->assertSee('Contacts')->assertDontSee('Projects')->assertDontSee('Contact Log');
        // In the merged tab a Sub Admin still gets hosting only, as before.
        $this->actingAs($this->sub)->get(route('clients.show', ['client' => $own, 'tab' => 'renewals']))->assertOk()->assertSee('Hosting records')->assertDontSee('hidden-domain.test');
        Livewire::actingAs($this->sub)->test(Renewals::class, ['clientId' => $own->id])->assertForbidden();
        Livewire::actingAs($this->sub)->test(Timeline::class, ['clientId' => $own->id])->assertForbidden();

        // The new team pages load for staff, and the existing client screens still do.
        foreach (['projects.index', 'renewals.index', 'clients.index', 'tasks.index', 'dashboard'] as $route) {
            $this->actingAs($this->plainStaff)->get(route($route))->assertOk();
        }
        foreach (['contacts', 'log', 'tasks', 'projects', 'support', 'renewals', 'hosting', 'finance', 'quotes'] as $tab) {
            $this->actingAs($this->admin)->get(route('clients.show', ['client' => $client, 'tab' => $tab]))->assertOk();
        }
    }
}
