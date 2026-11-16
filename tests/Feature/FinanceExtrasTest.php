<?php

namespace Tests\Feature;

use App\Livewire\Finance\Reports;
use App\Livewire\Invoices\Recurring;
use App\Livewire\Work\BillHours;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FinanceExtrasTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected User $staff;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-12 06:00:00');   // Thursday 12 Nov 2026
        $this->admin = User::factory()->superAdmin()->create(['name' => 'Asha Admin']);
        $this->staff = User::factory()->create(['name' => 'Ravi Staff', 'role' => User::ROLE_STAFF, 'permissions' => ['projects.view']]);
        $this->client = Client::create(['name' => 'Kerala Traders']);
    }

    public function test_recurring_invoices_raise_drafts_on_their_dates(): void
    {
        $this->actingAs($this->staff)->get('/recurring-invoices')->assertForbidden();
        Livewire::actingAs($this->staff)->test(Recurring::class)->assertForbidden();
        $this->actingAs($this->admin)->get('/recurring-invoices')->assertOk()->assertSee('No recurring invoices yet');

        // A monthly maintenance plan starting next month.
        $page = Livewire::actingAs($this->admin)->test(Recurring::class)->call('edit')->call('save')->assertHasErrors(['form.client_id', 'form.name', 'items.0.description', 'items.0.unit_price']);
        $page->set('form.client_id', (string) $this->client->id)->set('form.name', 'Website maintenance')->set('form.next_date', '2026-12-01')->set('form.tax_rate', '18')->set('form.due_days', '10')
            ->set('items.0.description', 'Website maintenance - {month} {year}')->set('items.0.unit_price', '5000')
            ->call('addItem')->set('items.1.description', 'Hosting')->set('items.1.quantity', '2')->set('items.1.unit_price', '750')
            ->assertSee('6,500.00')->call('save')->assertHasNoErrors()->assertSee('Website maintenance');
        $template = RecurringInvoice::firstOrFail();
        $this->assertSame([6500.0, 2, true], [$template->amount(), $template->items()->count(), $template->active]);

        // Not due yet: nothing is raised.
        $this->assertSame(0, RecurringInvoice::generateDue());
        // On 1 December a draft appears with its own month in the line, and the template moves on.
        $this->travelTo('2026-12-01 06:00:00');
        $this->actingAs($this->admin)->get('/invoices')->assertOk()->assertSee('1 recurring invoice raised as a draft');
        $invoice = Invoice::firstOrFail();
        $this->assertSame(['draft', '2026-12-01', '2026-12-11', '6500.00', '1170.00', '7670.00', $template->id],
            [$invoice->status, $invoice->invoice_date->toDateString(), $invoice->due_date->toDateString(), $invoice->subtotal, $invoice->tax_amount, $invoice->total, $invoice->recurring_invoice_id]);
        $this->assertSame(['Website maintenance - December 2026', 'Hosting'], $invoice->items->pluck('description')->all());
        $this->assertSame('2027-01-01', $template->fresh()->next_date->toDateString());
        // Opening the page again does not raise it twice.
        $this->actingAs($this->admin)->get('/invoices')->assertOk();
        $this->assertSame(1, Invoice::count());

        // After a long gap it catches up month by month; a paused one raises nothing.
        $this->travelTo('2027-03-05 06:00:00');
        $this->artisan('crm:generate-recurring-invoices')->expectsOutputToContain('3 recurring invoice(s) raised')->assertSuccessful();
        $this->assertSame(['2027-01-01', '2027-02-01', '2027-03-01'], Invoice::where('id', '>', $invoice->id)->orderBy('id')->get()->map(fn ($i) => $i->invoice_date->toDateString())->all());
        Livewire::actingAs($this->admin)->test(Recurring::class)->call('toggle', $template->id)->assertSee('Paused');
        $this->travelTo('2027-05-05 06:00:00');
        $this->assertSame(0, RecurringInvoice::generateDue());

        // "Raise now" makes the next one early; an end date finishes the template.
        Livewire::actingAs($this->admin)->test(Recurring::class)->call('toggle', $template->id)->call('edit', $template->id)->set('form.end_date', '2027-04-30')->call('save')->assertHasNoErrors()
            ->call('generateNow', $template->id)->assertRedirect();
        $this->assertSame([5, false, true], [Invoice::count(), $template->fresh()->active, $template->fresh()->isFinished()]);
        Livewire::actingAs($this->admin)->test(Recurring::class)->assertSee('Finished')->call('delete', $template->id);
        $this->assertSame([0, 5], [RecurringInvoice::count(), Invoice::count()]);
    }

    public function test_tracked_hours_are_billed_once_onto_a_draft_invoice(): void
    {
        $project = Project::create(['client_id' => $this->client->id, 'name' => 'Website redesign', 'kind' => 'project', 'status' => 'active']);
        $entry = fn (User $u, string $date, int $minutes, bool $billable = true) => TimeEntry::create(['user_id' => $u->id, 'project_id' => $project->id, 'work_date' => $date, 'minutes' => $minutes, 'is_billable' => $billable]);
        $entry($this->staff, '2026-11-02', 120);
        $entry($this->staff, '2026-11-03', 80);
        $entry($this->admin, '2026-11-04', 60);
        $entry($this->staff, '2026-11-05', 300, false);   // not billable

        // The panel is for finance only.
        $this->actingAs($this->staff)->get('/projects/'.$project->id)->assertOk()->assertDontSee('Bill hours');
        Livewire::actingAs($this->staff)->test(BillHours::class, ['projectId' => $project->id])->assertForbidden();
        $this->actingAs($this->admin)->get('/projects/'.$project->id)->assertOk()->assertSee('Bill hours')->assertSee('4h 20m');

        // 4h 20m billable at 1,500 an hour, one line per person.
        $panel = Livewire::actingAs($this->admin)->test(BillHours::class, ['projectId' => $project->id])->call('open')->assertSet('from', '2026-11-02')
            ->call('createInvoice')->assertHasErrors('rate')
            ->set('rate', '1500')->assertSee('Ravi Staff')->assertSee('3h 20m')->assertSee('6,495.00')
            ->set('lines', 'person')->call('createInvoice')->assertHasNoErrors()->assertRedirect();
        $invoice = Invoice::firstOrFail();
        $this->assertSame(['draft', $this->client->id, '6495.00'], [$invoice->status, $invoice->client_id, $invoice->subtotal]);
        $this->assertSame([['3.33', '1500.00', '4995.00'], ['1.00', '1500.00', '1500.00']], $invoice->items->map(fn ($i) => [$i->quantity, $i->unit_price, $i->amount])->all());
        $this->assertStringContainsString('Website redesign: Ravi Staff, hours worked 02 Nov 2026 to 03 Nov 2026', $invoice->items->first()->description);
        $this->assertSame([3, 1, '1500.00'], [TimeEntry::where('invoice_id', $invoice->id)->count(), TimeEntry::whereNull('invoice_id')->count(), $project->fresh()->hourly_rate]);

        // The same hours are not offered again; new hours are, at the remembered rate.
        Livewire::actingAs($this->admin)->test(BillHours::class, ['projectId' => $project->id])->assertViewHas('unbilled', 0)->call('open')->assertSet('rate', '1500')
            ->call('createInvoice')->assertHasErrors('from');
        $entry($this->staff, '2026-11-10', 90);
        Livewire::actingAs($this->admin)->test(BillHours::class, ['projectId' => $project->id])->assertViewHas('unbilled', 90)->assertViewHas('billed', 260);

        // Voiding the invoice frees its hours to be billed again.
        $invoice->update(['status' => 'void']);
        $this->assertSame(0, TimeEntry::whereNotNull('invoice_id')->count());
        Livewire::actingAs($this->admin)->test(BillHours::class, ['projectId' => $project->id])->assertViewHas('unbilled', 350);
    }

    public function test_reports_add_up_money_in_and_out_dues_and_leads(): void
    {
        $this->actingAs($this->staff)->get('/reports')->assertForbidden();
        $other = Client::create(['name' => 'Malabar Foods']);

        // Financial year April 2026 to March 2027.
        Payment::create(['client_id' => $this->client->id, 'amount' => 40000, 'payment_date' => '2026-05-10']);
        Payment::create(['client_id' => $this->client->id, 'amount' => 20000, 'payment_date' => '2026-11-03']);
        Payment::create(['client_id' => $other->id, 'amount' => 15000, 'payment_date' => '2026-11-05']);
        Payment::create(['client_id' => $other->id, 'amount' => 99999, 'payment_date' => '2026-02-01']);   // last financial year
        Expense::create(['expense_date' => '2026-11-08', 'amount' => 50000]);
        Expense::create(['expense_date' => '2026-06-01', 'amount' => 5000]);
        // Two unpaid invoices: one not due, one 40 days late with part paid. A draft is ignored.
        Invoice::create(['number' => 'INV-A', 'client_id' => $this->client->id, 'invoice_date' => '2026-11-01', 'due_date' => '2026-11-30', 'subtotal' => 10000, 'total' => 11800, 'status' => 'sent']);
        $late = Invoice::create(['number' => 'INV-B', 'client_id' => $other->id, 'invoice_date' => '2026-09-20', 'due_date' => '2026-10-03', 'subtotal' => 20000, 'total' => 23600, 'amount_paid' => 3600, 'status' => 'partially_paid']);
        Invoice::create(['number' => 'INV-C', 'client_id' => $other->id, 'invoice_date' => '2026-11-01', 'subtotal' => 7000, 'total' => 7000, 'status' => 'draft']);
        foreach (['won', 'won', 'lost', 'new'] as $i => $stage) {
            Lead::create(['name' => 'Lead '.$i, 'stage' => $stage]);
        }

        $page = Livewire::actingAs($this->admin)->test(Reports::class)->assertSee('01 Apr 2026 to 31 Mar 2027');
        $page->assertViewHas('totals', fn ($t) => $t === ['invoiced' => 30000.0, 'received' => 75000.0, 'spent' => 55000.0, 'profit' => 20000.0])
            ->assertViewHas('months', fn ($m) => $m->count() === 12 && $m->firstWhere('label', 'Nov 2026')['profit'] === -15000.0 && $m->firstWhere('label', 'May 2026')['received'] === 40000.0)
            ->assertViewHas('clients', fn ($c) => $c->pluck('name')->all() === ['Kerala Traders', 'Malabar Foods'] && (float) $c->first()->received === 60000.0)
            ->assertViewHas('buckets', fn ($b) => $b['Not due yet'] === 11800.0 && $b['31 to 60 days late'] === 20000.0 && array_sum($b) === 31800.0)
            ->assertViewHas('owing', fn ($o) => $o->first()['name'] === 'Malabar Foods' && $o->first()['late'] === 40)
            ->assertViewHas('leads', fn ($l) => [$l['total'], $l['won'], $l['lost'], $l['open'], $l['rate']] === [4, 2, 1, 1, 67.0])
            ->assertSee('Still owed by clients')->assertSee('Malabar Foods');

        // Another period.
        $page->set('period', 'last_fy')->assertSee('01 Apr 2025 to 31 Mar 2026')->assertViewHas('totals', fn ($t) => $t['received'] === 99999.0 && $t['spent'] === 0.0);
        $page->set('period', 'nonsense')->assertSet('period', 'fy');
    }
}
