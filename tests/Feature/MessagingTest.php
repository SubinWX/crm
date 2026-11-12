<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppMessage;
use App\Livewire\Invoices\Show as InvoiceShow;
use App\Livewire\Settings\Messaging;
use App\Livewire\Users\Index as UsersIndex;
use App\Livewire\Work\Renewals;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\MessageLog;
use App\Models\Renewal;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReminderService;
use App\Support\MailSettings;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-12 06:00:00'); // 11:30 IST
        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_access_options_are_set_per_person(): void
    {
        $client = Client::create(['name' => 'Acme']);
        Invoice::create(['number' => 'INV-A-1', 'client_id' => $client->id, 'invoice_date' => '2026-11-01', 'total' => 100, 'status' => 'sent']);

        // Staff: finance is now something a Super Admin can switch on.
        $form = Livewire::actingAs($this->admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('create')
            ->set('form.name', 'Fin Staff')->set('form.email', 'fin@example.com')->set('form.password', 'secret-pass-1')->set('form.role', 'staff')
            ->assertSee('Invoices, payments, expenses and GST reports')->set('form.permissions', ['finance.manage', 'tasks.manage', 'made.up'])
            ->call('save')->assertHasErrors('form.permissions.2');
        $form->set('form.permissions', ['finance.manage', 'tasks.manage'])->call('save')->assertHasNoErrors();
        $staff = User::where('email', 'fin@example.com')->firstOrFail();
        $this->assertSame(['tasks.manage', 'finance.manage'], $staff->permissions);
        $this->flushSession()->actingAs($staff)->get(route('invoices.index'))->assertOk()->assertSee('INV-A-1');
        $this->flushSession()->actingAs($staff)->get(route('expenses.index'))->assertOk();
        $this->flushSession()->actingAs($staff)->get(route('users.index'))->assertForbidden();
        $this->flushSession()->actingAs($staff)->get(route('settings.messaging'))->assertForbidden();
        $this->flushSession()->actingAs($staff)->get(route('leads.index'))->assertForbidden();

        // Sub Admin: own clients as always, plus only the extra areas ticked. Options that do not exist for the role are dropped.
        Livewire::actingAs($this->admin)->test(UsersIndex::class)->call('create')
            ->set('form.name', 'Sub One')->set('form.email', 'sub@example.com')->set('form.password', 'secret-pass-1')->set('form.role', 'sub_admin')
            ->assertSee('not only this person’s clients')->assertDontSee('Create and update tasks')
            ->set('form.permissions', ['careers.manage', 'tasks.manage', 'clients.manage'])->call('save')->assertHasNoErrors();
        $sub = User::where('email', 'sub@example.com')->firstOrFail();
        $this->assertSame(['careers.manage'], $sub->permissions);
        $this->assertTrue($sub->can('careers.manage') && $sub->can('clients.view'));
        $this->assertFalse($sub->can('finance') || $sub->can('tasks.manage') || $sub->can('clients.manage') || $sub->can('workspace'));
        $this->flushSession()->actingAs($sub)->get(route('careers.manage'))->assertOk();
        $this->flushSession()->actingAs($sub)->get(route('invoices.index'))->assertForbidden();
        $this->flushSession()->actingAs($sub)->get(route('clients.show', $client))->assertForbidden(); // still only their own clients

        // Promoting to Super Admin clears the list (they have everything); removing a tick takes access away at once.
        Livewire::actingAs($this->admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('edit', $staff->id)->assertSet('form.permissions', ['tasks.manage', 'finance.manage'])
            ->set('form.permissions', ['tasks.manage'])->call('save')->assertHasNoErrors();
        $this->flushSession()->actingAs($staff->fresh())->get(route('invoices.index'))->assertForbidden();
        Livewire::actingAs($this->admin)->test(UsersIndex::class)->call('edit', $sub->id)->set('form.role', 'super_admin')->call('save')->assertHasNoErrors();
        $this->assertNull($sub->fresh()->permissions);
        $this->assertTrue($sub->fresh()->can('finance'));
    }

    public function test_a_super_admin_connects_the_email_server(): void
    {
        $this->assertFalse(MailSettings::enabled());
        $page = Livewire::actingAs($this->admin)->test(Messaging::class)->assertSee('Email server (SMTP)');

        // Switching it on needs the server details; the host is a name, not a web address.
        $page->set('mail.mail_enabled', '1')->call('saveMail')->assertHasErrors(['mail.mail_host', 'mail.mail_from_address']);
        $page->set('mail.mail_host', 'https://smtp.example.com')->set('mail.mail_from_address', 'hello@acme.example')->call('saveMail')->assertHasErrors('mail.mail_host');

        $page->set('mail.mail_host', 'smtp.example.com')->set('mail.mail_port', '465')->set('mail.mail_encryption', 'ssl')->set('mail.mail_username', 'hello@acme.example')
            ->set('mailPassword', 'app-pass-123')->set('mail.mail_from_name', 'Acme Studio')->set('mail.mail_reply_to', 'support@acme.example')
            ->call('saveMail')->assertHasNoErrors()->assertSet('mailPassword', '');

        // The password is stored encrypted and never rendered back.
        $this->assertNotSame('app-pass-123', DB::table('settings')->where('key', 'mail_password')->value('value'));
        $this->assertSame('app-pass-123', MailSettings::secret('mail_password'));
        $page->assertDontSee('app-pass-123')->assertSee('A password is saved');

        $this->assertTrue(MailSettings::enabled());
        $this->assertSame(['smtp', 'smtp.example.com', 465, 'smtps', 'hello@acme.example', 'app-pass-123', 'hello@acme.example', 'Acme Studio', 'support@acme.example'], [
            config('mail.default'), config('mail.mailers.smtp.host'), config('mail.mailers.smtp.port'), config('mail.mailers.smtp.scheme'), config('mail.mailers.smtp.username'),
            config('mail.mailers.smtp.password'), config('mail.from.address'), config('mail.from.name'), config('mail.reply_to.address'),
        ]);

        // Saving again without typing a password keeps the saved one.
        Livewire::actingAs($this->admin)->test(Messaging::class)->assertSet('mail.mail_host', 'smtp.example.com')->set('mail.mail_port', '587')->set('mail.mail_encryption', 'tls')->call('saveMail')->assertHasNoErrors();
        $this->assertSame('app-pass-123', MailSettings::secret('mail_password'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));

        // The test button sends immediately and reports the outcome.
        Mail::fake();
        Livewire::actingAs($this->admin)->test(Messaging::class)->set('testEmail', 'owner@acme.example')->call('sendTestEmail')
            ->assertSet('mailResult.ok', true)->assertSee('Sent to owner@acme.example');

        // Switching it off goes back to the server default.
        Livewire::actingAs($this->admin)->test(Messaging::class)->set('mail.mail_enabled', '0')->call('saveMail')->assertHasNoErrors();
        $this->assertFalse(MailSettings::enabled());
    }

    public function test_whatsapp_reminders_reach_clients_once_per_reminder_date(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TEST1']]])]);

        $client = Client::create(['name' => 'Acme Traders', 'phone1' => '98765 43210']);
        $noPhone = Client::create(['name' => 'No Phone Co']);
        $due = Invoice::create(['number' => 'INV-W-1', 'client_id' => $client->id, 'invoice_date' => '2026-11-01', 'due_date' => '2026-11-12', 'total' => 11800, 'amount_paid' => 1800, 'status' => 'partially_paid']);
        $paid = Invoice::create(['number' => 'INV-W-2', 'client_id' => $client->id, 'invoice_date' => '2026-11-01', 'due_date' => '2026-11-12', 'total' => 500, 'amount_paid' => 500, 'status' => 'paid']);
        $later = Invoice::create(['number' => 'INV-W-3', 'client_id' => $client->id, 'invoice_date' => '2026-11-01', 'due_date' => '2026-11-30', 'total' => 900, 'status' => 'sent']);
        $silent = Invoice::create(['number' => 'INV-W-4', 'client_id' => $noPhone->id, 'invoice_date' => '2026-11-01', 'due_date' => '2026-11-05', 'total' => 700, 'status' => 'sent']);
        Renewal::create(['client_id' => $client->id, 'type' => 'domain', 'name' => 'acme.example', 'renewal_date' => '2026-11-19', 'amount' => 1200, 'status' => 'active']);

        // Before any API is connected, a WhatsApp button still opens a chat with the reminder typed; without a phone it is disabled.
        $this->actingAs($this->admin)->get(route('invoices.show', $due))->assertOk()->assertSee('WhatsApp reminder')
            ->assertSee('https://wa.me/919876543210?text=Hello%20Acme%20Traders%2C%20this%20is%20a%20reminder%20that%20invoice%20INV-W-1', false)->assertDontSee('wire:click="sendWhatsApp"', false);
        $this->actingAs($this->admin)->get(route('invoices.show', $silent))->assertOk()->assertSee('you choose the contact', false)->assertSee('https://wa.me/?text=Hello%20No%20Phone%20Co', false);
        $this->actingAs($this->admin)->get(route('invoices.show', $paid))->assertOk()->assertDontSee('WhatsApp reminder');
        $this->actingAs($this->admin)->get(route('renewals.index', ['type' => 'domain']))->assertOk()->assertSee('https://wa.me/919876543210?text=Hello%20Acme%20Traders%2C%20acme.example%20is%20due%20for%20renewal', false);

        // Nothing goes out until it is switched on and fully set up.
        $this->assertSame(0, app(ReminderService::class)->run()['whatsapp']);
        $this->assertSame(['API token', 'Phone number ID'], WhatsApp::missing());

        $page = Livewire::actingAs($this->admin)->test(Messaging::class);
        $page->set('wa.whatsapp_enabled', '1')->call('saveWhatsApp')->assertHasErrors('wa.whatsapp_phone_number_id');
        $page->set('wa.whatsapp_number', '+91 90000 00000')->set('wa.whatsapp_phone_number_id', '123456789012345')->set('waToken', 'EAAG-secret-token')
            ->set('wa.whatsapp_send_invoice', '1')->set('wa.whatsapp_send_renewal', '1')->set('wa.whatsapp_template_invoice', 'Payment Reminder')
            ->call('saveWhatsApp')->assertHasErrors('wa.whatsapp_template_invoice');
        $page->set('wa.whatsapp_template_invoice', 'payment_reminder')->call('saveWhatsApp')->assertHasNoErrors()->assertSet('waToken', '')->assertDontSee('EAAG-secret-token');
        $this->assertNotSame('EAAG-secret-token', DB::table('settings')->where('key', 'whatsapp_token')->value('value'));
        $this->assertTrue(WhatsApp::enabled());
        $this->assertSame('919876543210', WhatsApp::normalizePhone('98765 43210'));
        $this->assertSame('971501234567', WhatsApp::normalizePhone('+971 50 123 4567'));
        $this->assertNull(WhatsApp::normalizePhone('12345'));

        // Due today (offset 0), a week overdue with no phone (skipped, recorded), and a domain at its 7-day stage. Paid and far-off invoices are left alone.
        $this->assertSame(3, app(ReminderService::class)->run()['whatsapp']);
        $invoiceLog = MessageLog::where('event', 'invoice_due')->where('subject_id', $due->id)->firstOrFail();
        $this->assertSame(['919876543210', 'sent', 'wamid.TEST1', '2026-11-12'], [$invoiceLog->recipient, $invoiceLog->status, $invoiceLog->provider_message_id, $invoiceLog->scheduled_date->toDateString()]);
        $this->assertStringContainsString('Hello Acme Traders, this is a reminder that invoice INV-W-1 for ₹10,000.00 is due on 12 Nov 2026.', $invoiceLog->body);
        $this->assertSame('skipped', MessageLog::where('subject_id', $silent->id)->where('event', 'invoice_due')->value('status'));
        $this->assertSame('sent', MessageLog::where('event', 'renewal_due')->value('status'));
        $this->assertSame(0, MessageLog::whereIn('subject_id', [$paid->id, $later->id])->where('subject_type', 'invoice')->count());

        // An invoice reminder with a template name goes as that approved template with the four values in order; the renewal went as text.
        Http::assertSent(fn ($request) => $request->url() === 'https://graph.facebook.com/v21.0/123456789012345/messages' && $request->hasHeader('Authorization', 'Bearer EAAG-secret-token')
            && $request['to'] === '919876543210' && $request['type'] === 'template' && $request['template']['name'] === 'payment_reminder'
            && array_column($request['template']['components'][0]['parameters'], 'text') === ['Acme Traders', 'INV-W-1', '12 Nov 2026', '₹10,000.00']);
        Http::assertSent(fn ($request) => ($request['type'] ?? null) === 'text' && str_contains($request['text']['body'], 'acme.example is due for renewal on 19 Nov 2026'));

        // Now that the API is connected, the same buttons send through it instead of opening WhatsApp.
        $this->actingAs($this->admin)->get(route('invoices.show', $due))->assertOk()->assertSee('wire:click="sendWhatsApp"', false)->assertSee('Send WhatsApp reminder')->assertDontSee('https://wa.me/', false);
        $renewal = Renewal::firstOrFail();
        $this->actingAs($this->admin)->get(route('renewals.index', ['type' => 'domain']))->assertOk()->assertSee('sendWhatsApp('.$renewal->id.')', false)->assertDontSee('https://wa.me/', false);
        Livewire::actingAs($this->admin)->test(Renewals::class)->call('sendWhatsApp', $renewal->id);
        $sentByHand = MessageLog::where('event', 'manual')->where('subject_type', 'renewal')->firstOrFail();
        $this->assertSame(['sent', '919876543210'], [$sentByHand->status, $sentByHand->recipient]);
        MessageLog::where('event', 'manual')->delete();

        // Running the scheduler again the same day sends nothing new; the next configured day (a week overdue) sends once more.
        $this->assertSame(0, app(ReminderService::class)->run()['whatsapp']);
        $this->travelTo('2026-11-19 06:00:00');
        $this->assertSame(1, MessageLog::where('subject_id', $due->id)->where('event', 'invoice_due')->count());
        app(ReminderService::class)->run();
        $this->assertSame(['2026-11-12', '2026-11-19'], MessageLog::where('subject_id', $due->id)->where('event', 'invoice_due')->orderBy('scheduled_date')->get()->map(fn ($m) => $m->scheduled_date->toDateString())->all());

        // A reminder can also be sent by hand from the invoice; a provider error is recorded, not hidden.
        Http::swap(new Factory);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template name does not exist']], 400)]);
        Livewire::actingAs($this->admin)->test(InvoiceShow::class, ['invoice' => $due])->assertSee('Send WhatsApp reminder')->call('sendWhatsApp');
        $manual = MessageLog::where('event', 'manual')->firstOrFail();
        $this->assertSame(['failed', 'Template name does not exist'], [$manual->status, $manual->error]);
        (new SendWhatsAppMessage($manual->id))->handle(); // a failed message is not sent again
        $this->assertSame('failed', $manual->fresh()->status);
        Livewire::actingAs($this->admin)->test(Messaging::class)->assertSee('Recent WhatsApp messages')->assertSee('Template name does not exist');

        // Another provider: the address, header and JSON body are configurable, and must be a public https address.
        Http::fake(['api.provider.example/*' => Http::response(['id' => 'abc-1'])]);
        $custom = Livewire::actingAs($this->admin)->test(Messaging::class)->set('wa.whatsapp_provider', 'custom')
            ->set('wa.whatsapp_custom_url', 'http://169.254.169.254/latest')->call('saveWhatsApp')->assertHasErrors('wa.whatsapp_custom_url');
        $custom->set('wa.whatsapp_custom_url', 'https://127.0.0.1/send')->call('saveWhatsApp')->assertHasErrors('wa.whatsapp_custom_url');
        $custom->set('wa.whatsapp_custom_url', 'https://api.provider.example/v1/send')->set('wa.whatsapp_custom_auth_header', 'X-API-Key')
            ->set('wa.whatsapp_custom_body', '{"number": "{phone}", "text": "{message}", "channel": "whatsapp"}')->set('waToken', 'key-777')
            ->call('saveWhatsApp')->assertHasNoErrors();
        $custom->set('testPhone', '123')->call('sendTestWhatsApp')->assertHasErrors('testPhone');
        $custom->set('testPhone', '+91 98765 43210')->call('sendTestWhatsApp')->assertSet('waResult.ok', true);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.provider.example/v1/send' && $request->hasHeader('X-API-Key', 'key-777')
            && $request['number'] === '919876543210' && $request['channel'] === 'whatsapp' && str_contains($request['text'], 'Test message from'));

        // Removing the token stops everything.
        Livewire::actingAs($this->admin)->test(Messaging::class)->call('removeWhatsAppToken');
        Setting::flush();
        $this->assertFalse(WhatsApp::enabled());
    }
}
