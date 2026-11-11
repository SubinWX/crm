<?php

namespace Tests\Feature;

use App\Jobs\DeliverReminder;
use App\Livewire\Clients\Form as ClientForm;
use App\Livewire\Hosting\Index as HostingIndex;
use App\Livewire\Invoices\Form as InvoiceForm;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Payments\Form as PaymentForm;
use App\Models\Client;
use App\Models\HostingRecord;
use App\Models\Invoice;
use App\Models\Lookup;
use App\Models\Payment;
use App\Models\ReminderLog;
use App\Models\User;
use App\Services\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The end-to-end path from the brief: add a client → hosting reminders → record a
 * renewal → invoice planned-send reminder → mark sent → partial payment → PDF.
 */
class CrmWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected User $admin2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-10 10:00:00'); // 15:30 IST
        $this->admin = User::factory()->superAdmin()->create(['name' => 'Asha Admin']);
        $this->admin2 = User::factory()->superAdmin()->create(['name' => 'Bala Admin']);
    }

    protected function lookup(string $type, string $name): int
    {
        return Lookup::where('type', $type)->where('name', $name)->value('id');
    }

    protected function sentMailCount(): int
    {
        return app('mailer')->getSymfonyTransport()->messages()->count();
    }

    protected function notificationCount(string $category): int
    {
        return DB::table('notifications')->where('data->category', $category)->count();
    }

    public function test_complete_workflow(): void
    {
        $staff = User::factory()->staff(['clients.manage'])->create(['name' => 'Sita Staff']);
        $inactiveAdmin = User::factory()->superAdmin()->inactive()->create();

        // ---- 1. Staff adds a client with a Paid hosting record due in exactly two months
        Livewire::actingAs($staff)->test(ClientForm::class)
            ->set('form.name', 'Acme Retail')
            ->set('form.email', 'owner@acme.test')
            ->set('workTypeIds', [$this->lookup('work_type', 'Hosting'), $this->lookup('work_type', 'SEO')])
            ->set('addHosting', true)
            ->set('hosting.domain', 'acme.test')
            ->set('hosting.server_type_id', $this->lookup('server_type', 'Paid'))
            ->set('hosting.billing_start', '2026-01-10')
            ->set('hosting.billing_end', '2027-01-09')
            ->set('hosting.next_payment_date', '2027-01-10')
            ->set('hosting.renewal_amount', '6500')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $client = Client::where('name', 'Acme Retail')->firstOrFail();
        $this->assertSame(2, $client->workTypes()->count());

        // Every ACTIVE Super Admin gets an in-app notification and an email.
        $this->assertSame(2, $this->notificationCount('client'));
        $this->assertSame(1, $this->admin->notifications()->count());
        $this->assertSame(0, $inactiveAdmin->notifications()->count());
        $this->assertSame(0, $staff->notifications()->count());
        $this->assertSame(2, $this->sentMailCount());
        $notification = $this->admin->notifications()->first();
        $this->assertStringContainsString('Acme Retail', $notification->data['title']);
        $this->assertStringContainsString('Sita Staff', $notification->data['message']);
        $this->assertSame(route('clients.show', $client), $notification->data['url']);

        // ---- 2. Hosting reminders: two months before, then seven days before
        $hosting = $client->hostingRecords()->firstOrFail();
        $reminders = app(ReminderService::class);

        $reminders->run(); // today is 10 Nov 2026 = exactly two calendar months before 10 Jan 2027
        $this->assertSame(2, $this->notificationCount('hosting'));
        $this->assertSame(4, ReminderLog::where('event', 'hosting_two_months')->whereNotNull('sent_at')->count());
        $this->assertSame(4, $this->sentMailCount());
        $message = $this->admin->notifications()->where('data->category', 'hosting')->first()->data['message'];
        foreach (['Acme Retail', 'acme.test', '6,500', '10 Jan 2027'] as $expected) {
            $this->assertStringContainsString($expected, $message);
        }

        // Scheduler rerun and a later day: no duplicates.
        $reminders->run();
        $this->travelTo('2026-11-20 10:00:00');
        $reminders->run();
        $this->assertSame(2, $this->notificationCount('hosting'));
        $this->assertSame(4, $this->sentMailCount());

        // Seven days before is a distinct reminder.
        $this->travelTo('2027-01-03 03:00:00');
        $reminders->run();
        $reminders->run();
        $this->assertSame(4, $this->notificationCount('hosting'));
        $this->assertSame(4, ReminderLog::where('event', 'hosting_seven_days')->whereNotNull('sent_at')->count());
        $this->assertSame(6, $this->sentMailCount());

        // ---- 3. Record Renewal stores the completed period and rolls the date forward
        Livewire::actingAs($staff)->test(HostingIndex::class, ['clientId' => $client->id])
            ->call('startRenewal', $hosting->id)
            ->assertSet('renewal.next_payment_date', '2028-01-10')
            ->assertSet('renewal.new_billing_start', '2027-01-10')
            ->call('saveRenewal')
            ->assertHasNoErrors();

        $hosting->refresh();
        $renewal = $hosting->renewals()->firstOrFail();
        $this->assertSame('2026-01-10', $renewal->period_start->toDateString());
        $this->assertSame('2027-01-09', $renewal->period_end->toDateString());
        $this->assertSame('2027-01-10', $renewal->payment_due_date->toDateString());
        $this->assertSame('2028-01-10', $hosting->next_payment_date->toDateString());
        $this->assertSame('2027-01-10', $hosting->billing_start->toDateString());
        $this->assertSame('2028-01-09', $hosting->billing_end->toDateString());

        // Nothing is due for the new date yet; the next cycle fires on its own dates.
        $reminders->run();
        $this->assertSame(4, $this->notificationCount('hosting'));
        $this->travelTo('2027-11-10 10:00:00');
        $reminders->run();
        $this->assertSame(6, $this->notificationCount('hosting'));

        // ---- 4. Invoice: draft with a planned send date → internal reminder → mark sent
        $this->travelTo('2027-11-11 10:00:00');
        Livewire::actingAs($this->admin)->test(InvoiceForm::class)
            ->set('form.client_id', (string) $client->id)
            ->set('form.planned_send_date', '2027-11-12')
            ->set('form.tax_rate', '18')
            ->set('items', [
                ['description' => 'Website maintenance', 'quantity' => '2', 'unit_price' => '4000'],
                ['description' => 'SEO retainer', 'quantity' => '1', 'unit_price' => '2000'],
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $invoice = Invoice::firstOrFail();
        $this->assertSame('draft', $invoice->status);
        $this->assertEquals(10000.00, (float) $invoice->subtotal);
        $this->assertEquals(1800.00, (float) $invoice->tax_amount);
        $this->assertEquals(11800.00, (float) $invoice->total);
        // Drafts do not count towards the pending amount.
        $this->assertEquals(0.0, $client->financials()['pending']);

        $reminders->run(); // planned date is tomorrow
        $this->assertSame(0, $this->notificationCount('invoice'));
        $this->travelTo('2027-11-12 10:00:00');
        $reminders->run();
        $reminders->run();
        $this->assertSame(2, $this->notificationCount('invoice'));
        $this->assertStringContainsString($invoice->number, $this->admin2->notifications()->where('data->category', 'invoice')->first()->data['title']);

        Livewire::actingAs($this->admin)->test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('markSent')->assertHasNoErrors();
        $invoice->refresh();
        $this->assertSame('sent', $invoice->status);
        $this->assertNotNull($invoice->sent_at);
        $this->assertEquals(11800.00, $client->financials()['pending']);

        // ---- 5. Partial payment, then an unallocated payment that is applied later
        Livewire::actingAs($this->admin)->test(PaymentForm::class)
            ->set('form.client_id', (string) $client->id)
            ->set('form.payment_method_id', (string) $this->lookup('payment_method', 'UPI'))
            ->set('form.amount', '5000')
            ->set('form.received_by', (string) $this->admin2->id)
            ->set('form.reference', 'UTR123')
            ->set("allocations.{$invoice->id}", '5000')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertEquals(6800.00, $invoice->balance);
        $this->assertEquals(6800.00, $client->financials()['pending']);
        $this->assertEquals(0.0, $client->financials()['unallocated']);

        Livewire::actingAs($this->admin)->test(PaymentForm::class)
            ->set('form.client_id', (string) $client->id)
            ->set('form.payment_method_id', (string) $this->lookup('payment_method', 'Cash'))
            ->set('form.amount', '7000')
            ->set('form.received_by', (string) $this->admin->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals(6800.00, $client->financials()['pending']);
        $this->assertEquals(7000.00, $client->financials()['unallocated']);

        $second = Payment::where('amount', 7000)->firstOrFail();

        // Cannot apply more than the invoice balance.
        Livewire::actingAs($this->admin)->test(PaymentForm::class, ['payment' => $second])
            ->set("allocations.{$invoice->id}", '7000')
            ->call('save')
            ->assertHasErrors('allocations');

        Livewire::actingAs($this->admin)->test(PaymentForm::class, ['payment' => $second])
            ->call('autoAllocate')
            ->assertSet("allocations.{$invoice->id}", '6800')
            ->call('save')
            ->assertHasNoErrors();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertEquals(0.0, $client->financials()['pending']);
        $this->assertEquals(200.00, $client->financials()['unallocated']);

        // ---- 6. Download the invoice PDF
        $response = $this->actingAs($this->admin)->get(route('invoices.pdf', $invoice));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertStringContainsString($invoice->number.'.pdf', $response->headers->get('content-disposition'));

        // Activity log recorded who did what.
        $this->assertDatabaseHas('activity_logs', ['action' => 'created', 'subject_type' => 'client', 'user_id' => $staff->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'renewed', 'subject_type' => 'hosting', 'user_id' => $staff->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'sent', 'subject_type' => 'invoice', 'user_id' => $this->admin->id]);
    }

    public function test_reminders_are_recalculated_and_suppressed(): void
    {
        $client = Client::create(['name' => 'Beta Clinic']);
        $paid = $this->lookup('server_type', 'Paid');
        $reminders = app(ReminderService::class);

        // Today is 10 Nov 2026. Due in 5 days → only the seven-day reminder, not both at once.
        $hosting = HostingRecord::create(['client_id' => $client->id, 'domain' => 'beta.test', 'server_type_id' => $paid, 'next_payment_date' => '2026-11-15', 'renewal_amount' => 1200]);
        $reminders->run();
        $this->assertSame(2, $this->notificationCount('hosting'));
        $this->assertSame(0, ReminderLog::where('event', 'hosting_two_months')->count());

        // Billing date changes → reminders follow the new date.
        $hosting->update(['next_payment_date' => '2026-12-30']);
        $reminders->run(); // inside the two-month window of the new date
        $this->assertSame(4, $this->notificationCount('hosting'));
        $this->assertSame(1, ReminderLog::where('event', 'hosting_two_months')->where('channel', 'mail')->where('recipient_id', $this->admin->id)->count());
        $this->travelTo('2026-12-24 10:00:00');
        $reminders->run();
        $this->assertSame(6, $this->notificationCount('hosting'));

        // Suspended, Self-managed and archived-client records never remind.
        $suspended = HostingRecord::create(['client_id' => $client->id, 'domain' => 'old.test', 'server_type_id' => $paid, 'next_payment_date' => '2026-12-28', 'status' => 'suspended']);
        HostingRecord::create(['client_id' => $client->id, 'domain' => 'self.test', 'server_type_id' => $this->lookup('server_type', 'Self-managed'), 'next_payment_date' => '2026-12-28']);
        $archived = Client::create(['name' => 'Gone Ltd', 'archived_at' => now()]);
        HostingRecord::create(['client_id' => $archived->id, 'domain' => 'gone.test', 'server_type_id' => $paid, 'next_payment_date' => '2026-12-28']);
        $reminders->run();
        $this->assertSame(6, $this->notificationCount('hosting'));
        $this->assertSame(0, ReminderLog::where('subject_id', $suspended->id)->count());

        // An invoice already marked as sent before its planned date is not reminded.
        $invoice = Invoice::create(['number' => 'INV-T-1', 'client_id' => $client->id, 'invoice_date' => '2026-12-20', 'planned_send_date' => '2026-12-24', 'total' => 500, 'status' => 'draft']);
        $invoice->markSent();
        $draft = Invoice::create(['number' => 'INV-T-2', 'client_id' => $client->id, 'invoice_date' => '2026-12-20', 'planned_send_date' => '2026-12-24', 'total' => 500, 'status' => 'draft']);
        $reminders->run();
        $this->assertSame(2, $this->notificationCount('invoice'));
        $this->assertSame(0, ReminderLog::where('subject_type', 'invoice')->where('subject_id', $invoice->id)->count());

        // Moving the planned date schedules a fresh reminder for the new date only.
        $draft->update(['planned_send_date' => '2026-12-26']);
        $reminders->run();
        $this->assertSame(2, $this->notificationCount('invoice'));
        $this->travelTo('2026-12-26 10:00:00');
        $reminders->run();
        $this->assertSame(4, $this->notificationCount('invoice'));
    }

    public function test_a_claimed_reminder_is_delivered_once_even_if_the_job_runs_twice(): void
    {
        $client = Client::create(['name' => 'Retry Co']);
        HostingRecord::create(['client_id' => $client->id, 'domain' => 'retry.test', 'server_type_id' => $this->lookup('server_type', 'Paid'), 'next_payment_date' => '2026-11-12']);
        app(ReminderService::class)->run();

        $before = [$this->notificationCount('hosting'), $this->sentMailCount()];
        foreach (ReminderLog::all() as $log) {
            (new DeliverReminder($log->id))->handle(); // simulated queue retry
        }
        $this->assertSame($before, [$this->notificationCount('hosting'), $this->sentMailCount()]);
    }
}
