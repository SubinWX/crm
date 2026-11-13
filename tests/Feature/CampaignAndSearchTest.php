<?php

namespace Tests\Feature;

use App\Livewire\Campaigns\Compose;
use App\Livewire\Campaigns\Index;
use App\Livewire\Clients\Form;
use App\Livewire\Search\Box;
use App\Livewire\Settings\Messaging;
use App\Mail\CampaignMail;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailSettings;
use App\Support\Sms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class CampaignAndSearchTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create(['name' => 'Asha Admin', 'email' => 'asha@example.test']);
        $this->staff = User::factory()->create(['name' => 'Ravi Staff', 'role' => User::ROLE_STAFF, 'permissions' => ['clients.view']]);
        Setting::put('company_name', 'WX Agency');
        Client::create(['name' => 'Kerala Traders', 'contact_name' => 'Anil', 'email' => 'anil@kerala.test', 'phone1' => '98765 43210']);
        Client::create(['name' => 'Malabar Foods', 'email' => 'hello@malabar.test', 'status' => 'on_hold']);
        Client::create(['name' => 'No Email Co', 'phone1' => '9000000001']);
        Client::create(['name' => 'Opted Out Ltd', 'email' => 'no@optout.test', 'bulk_opt_out_at' => now()]);
        Client::create(['name' => 'Old Client', 'email' => 'old@client.test', 'archived_at' => now()]);
    }

    public function test_bulk_emails_go_to_the_selected_clients_with_names_filled_in(): void
    {
        Mail::fake();
        // Super Admins only.
        $this->actingAs($this->staff)->get('/bulk-messages')->assertForbidden();
        $this->actingAs($this->staff)->get('/bulk-messages/new')->assertForbidden();
        $this->actingAs($this->admin)->get('/bulk-messages')->assertOk()->assertSee('No bulk messages have been sent yet');

        // Active clients by default; no address, opted-out and archived clients are left out.
        $page = Livewire::actingAs($this->admin)->test(Compose::class)->assertSee('1 of 1 client selected')->assertSee('Kerala Traders')->assertSee('anil@kerala.test')
            ->assertSee('1 matching client has no email address')->assertSee('1 client has asked not to receive bulk messages')->assertDontSee('old@client.test')
            ->set('statuses', ['active', 'on_hold'])->assertSee('2 of 2 clients selected');
        $page->call('send')->assertHasErrors(['name', 'subject', 'body']);
        $page->set('name', 'Onam greetings')->set('subject', 'Happy Onam, {contact}')->set('body', '<div>Dear {contact} of <strong>{client}</strong>, greetings from {company}.</div><script>x</script>')
            ->assertSee('Happy Onam, Anil')
            ->call('sendTest');
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo('asha@example.test') && $m->subjectLine === 'Happy Onam, Anil');

        // Untick one client, then send.
        $malabar = Client::where('name', 'Malabar Foods')->firstOrFail();
        $page->set('excluded', [(string) $malabar->id])->assertSee('1 of 2 clients selected')->set('excluded', [])->call('send')->assertHasNoErrors()->assertRedirect();
        $campaign = Campaign::firstOrFail();
        $this->assertSame(['email', 2, 'sent', 2], [$campaign->channel, $campaign->total, $campaign->fresh()->status, $campaign->recipients()->where('status', 'sent')->count()]);
        $this->assertStringNotContainsString('<script>', $campaign->body);
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo('anil@kerala.test') && $m->subjectLine === 'Happy Onam, Anil' && str_contains($m->bodyHtml, 'Dear Anil of <strong>Kerala Traders</strong>, greetings from WX Agency.'));
        Mail::assertSent(CampaignMail::class, fn ($m) => $m->hasTo('hello@malabar.test') && $m->subjectLine === 'Happy Onam, Malabar Foods');
        Mail::assertNotSent(CampaignMail::class, fn ($m) => $m->hasTo('no@optout.test') || $m->hasTo('old@client.test'));

        // The list and the per-client results.
        Livewire::actingAs($this->admin)->withQueryParams(['open' => $campaign->id])->test(Index::class)->assertSee('Onam greetings')->assertSee('anil@kerala.test')->assertSee('Sent');

        // The email carries a "stop these emails" link; using it takes the client off future bulk messages only.
        $html = CampaignMail::for($campaign, Client::where('name', 'Kerala Traders')->first())->render();
        $this->assertStringContainsString('Stop these emails', $html);
        $kerala = Client::where('name', 'Kerala Traders')->firstOrFail();
        $this->get('/stop-emails/'.$kerala->id)->assertForbidden();   // not signed
        $this->get(URL::signedRoute('bulk.unsubscribe', ['client' => $kerala->id]))->assertOk()->assertSee('You will not get these emails any more');
        $this->assertNotNull($kerala->fresh()->bulk_opt_out_at);
        Livewire::actingAs($this->admin)->test(Compose::class)->assertSee('0 of 0 clients selected');

        // The tick box on the client form does the same.
        Livewire::actingAs($this->admin)->test(Form::class, ['client' => $kerala->fresh()])->assertSet('bulkOptOut', true)->set('bulkOptOut', false)->call('save')->assertHasNoErrors();
        $this->assertNull($kerala->fresh()->bulk_opt_out_at);
    }

    public function test_bulk_whatsapp_needs_whatsapp_set_up_and_records_each_result(): void
    {
        // Not set up: the message can be written but not sent.
        $page = Livewire::actingAs($this->admin)->test(Compose::class)->set('channel', 'whatsapp')->assertSee('WhatsApp is not set up yet')->assertSee('2 of 2 clients selected')->assertSee('919876543210')
            ->set('name', 'Diwali offer')->set('body', 'Hello {contact}, 20% off this week. - {company}')->call('send')->assertHasErrors('channel');
        $this->assertSame(0, Campaign::count());

        // A custom provider takes free text.
        Setting::put('whatsapp_enabled', '1');
        Setting::put('whatsapp_provider', 'custom');
        Setting::put('whatsapp_custom_url', 'https://wa.example.test/send');
        MailSettings::putSecret('whatsapp_token', 'secret-token');
        Http::fake(['wa.example.test/*' => Http::sequence()->push(['id' => 'm1'])->push('bad number', 400)->push(['id' => 'm2'])]);

        Livewire::actingAs($this->admin)->test(Compose::class)->set('channel', 'whatsapp')->assertDontSee('WhatsApp template name')
            ->set('name', 'Diwali offer')->set('body', 'Hello {contact}, 20% off this week. - {company}')->assertSee('Hello Anil, 20% off this week. - WX Agency')
            ->call('send')->assertHasNoErrors();
        $campaign = Campaign::firstOrFail();
        $this->assertSame([1, 1, 'sent'], [$campaign->recipients()->where('status', 'sent')->count(), $campaign->recipients()->where('status', 'failed')->count(), $campaign->fresh()->status]);
        Http::assertSent(fn ($r) => $r['to'] === '919876543210' && $r['message'] === 'Hello Anil, 20% off this week. - WX Agency');

        // Failed copies can be sent again.
        Livewire::actingAs($this->admin)->test(Index::class)->assertSee('Finished with failures')->call('retryFailed', $campaign->id);
        $this->assertSame(2, $campaign->recipients()->where('status', 'sent')->count());

        // Meta needs the name of an approved template.
        Setting::put('whatsapp_provider', 'meta');
        Setting::put('whatsapp_phone_number_id', '12345');
        Livewire::actingAs($this->admin)->test(Compose::class)->set('channel', 'whatsapp')->assertSee('WhatsApp template name')->set('name', 'x')->set('body', 'y')
            ->call('send')->assertHasErrors('whatsappTemplate')->set('whatsappTemplate', 'Bad Name')->call('send')->assertHasErrors('whatsappTemplate');
    }

    public function test_bulk_text_messages_go_out_once_an_sms_service_is_connected(): void
    {
        // Staff cannot reach the settings; nothing is connected yet.
        $this->actingAs($this->staff)->get('/settings/email-whatsapp')->assertForbidden();
        Livewire::actingAs($this->admin)->test(Compose::class)->set('channel', 'sms')->assertSee('No SMS service is connected yet')->assertSee('DLT template ID')
            ->set('name', 'Offer')->set('body', 'Hi {contact}')->call('send')->assertHasErrors('channel');

        // Connecting a service: a public https address is required, and the body must carry the phone and the message.
        $settings = Livewire::actingAs($this->admin)->test(Messaging::class)->assertSee('Text messages (SMS)')
            ->set('sms.sms_enabled', '1')->call('saveSms')->assertHasErrors('sms.sms_url')
            ->set('sms.sms_url', 'http://sms.example.test/send')->call('saveSms')->assertHasErrors('sms.sms_url')
            ->set('sms.sms_url', 'https://localhost/send')->call('saveSms')->assertHasErrors('sms.sms_url')
            ->set('sms.sms_url', 'https://sms.example.test/send')->set('sms.sms_body', '{"to": "{phone}"}')->call('saveSms')->assertHasErrors('sms.sms_body')
            ->set('sms.sms_body', '{"to": "{phone}", "text": "{message}", "from": "{sender}", "dlt": "{template_id}"}')
            ->set('sms.sms_sender_id', 'WXAGNC')->set('sms.sms_auth_header', 'authkey')->set('smsToken', 'sms-secret')->call('saveSms')->assertHasNoErrors()->assertSet('smsToken', '');
        $this->assertTrue(Sms::enabled());
        $this->assertNotSame('sms-secret', Setting::get('sms_token'));

        Http::fake(['sms.example.test/*' => Http::sequence()->push(['id' => 't1'])->push(['id' => 's1'])->push('DLT template mismatch', 400)]);
        $settings->set('testSmsPhone', '12')->call('sendTestSms')->assertHasErrors('testSmsPhone')
            ->set('testSmsPhone', '98765 43210')->call('sendTestSms')->assertHasNoErrors()->assertSee('Accepted by the SMS service for +919876543210');

        // A bulk text message: the two clients with a mobile number, each with their own wording and the template ID.
        Livewire::actingAs($this->admin)->test(Compose::class)->set('channel', 'sms')->assertDontSee('No SMS service is connected yet')->assertSee('2 of 2 clients selected')
            ->set('name', 'Diwali offer')->set('whatsappTemplate', 'bad id!')->set('body', 'Hi {contact}, "20% off" & more. -{company}')->assertSee('1 text message per client')
            ->call('send')->assertHasErrors('whatsappTemplate')
            ->set('whatsappTemplate', '1107161234567890123')->call('send')->assertHasNoErrors();
        $campaign = Campaign::where('channel', 'sms')->firstOrFail();
        $this->assertSame([2, 1, 1, '1107161234567890123'], [$campaign->total, $campaign->recipients()->where('status', 'sent')->count(), $campaign->recipients()->where('status', 'failed')->count(), $campaign->whatsapp_template]);
        Http::assertSent(fn ($r) => $r->hasHeader('authkey', 'sms-secret') && $r['to'] === '919876543210' && $r['text'] === 'Hi Anil, "20% off" & more. -WX Agency' && $r['from'] === 'WXAGNC' && $r['dlt'] === '1107161234567890123');
        $this->assertStringContainsString('DLT template mismatch', (string) $campaign->recipients()->where('status', 'failed')->first()->error);

        // Services that take everything in the address: each value is encoded, and the key goes where {token} is.
        $settings->set('sms.sms_method', 'get')->set('sms.sms_url', 'https://gw.example.test/api?to={phone}')->call('saveSms')->assertHasErrors('sms.sms_url')
            ->set('sms.sms_url', 'https://gw.example.test/api?key={token}&to={phone}&text={message}&sender={sender}')->call('saveSms')->assertHasNoErrors();
        Http::fake(['gw.example.test/*' => Http::response('OK')]);
        $this->assertTrue(Sms::send('919876543210', 'Hi & bye #1')['ok']);
        Http::assertSent(fn ($r) => $r->method() === 'GET' && $r->url() === 'https://gw.example.test/api?key=sms-secret&to=919876543210&text=Hi%20%26%20bye%20%231&sender=WXAGNC');

        // Removing the key switches it off.
        $settings->call('removeSmsToken');
        $this->assertFalse(Sms::enabled());
    }

    public function test_global_search_finds_records_only_in_the_areas_a_person_can_open(): void
    {
        $kerala = Client::where('name', 'Kerala Traders')->firstOrFail();
        Project::create(['client_id' => $kerala->id, 'name' => 'Kerala website redesign', 'kind' => 'project', 'status' => 'active']);
        $invoice = Invoice::create(['number' => 'INV-KER-77', 'client_id' => $kerala->id, 'invoice_date' => '2026-11-01', 'due_date' => '2026-11-22', 'total' => 900, 'status' => 'sent']);

        $this->actingAs($this->admin)->get('/')->assertOk()->assertSee('Search clients, invoices, projects');

        // One letter is not enough; two or more search everything.
        Livewire::actingAs($this->admin)->test(Box::class)->set('q', 'k')->assertDontSee('Kerala Traders')
            ->set('q', 'kerala')->assertSee('Clients')->assertSee('Kerala Traders')->assertSee('Projects')->assertSee('Kerala website redesign')->assertSee('Invoices')->assertSee($invoice->number)
            ->set('q', 'ravi')->assertSee('People')->assertSee('Ravi Staff')
            ->set('q', 'zzzz')->assertSee('Nothing found');
        // Phone numbers and a % typed in are matched as text.
        Livewire::actingAs($this->admin)->test(Box::class)->set('q', '98765')->assertSee('Kerala Traders')->set('q', '%%')->assertSee('Nothing found');

        // Staff with client access see clients and projects, not invoices or people.
        Livewire::actingAs($this->staff)->test(Box::class)->set('q', 'kerala')->assertSee('Kerala Traders')->assertSee('Kerala website redesign')->assertDontSee('Invoices')->assertDontSee($invoice->number)
            ->set('q', 'asha')->assertSee('Nothing found');
        // An Admin only finds the clients they added.
        $subAdmin = User::factory()->create(['role' => User::ROLE_SUB_ADMIN]);
        Livewire::actingAs($subAdmin)->test(Box::class)->set('q', 'kerala')->assertSee('Nothing found');
        $kerala->update(['created_by' => $subAdmin->id]);
        Livewire::actingAs($subAdmin)->test(Box::class)->set('q', 'kerala')->assertSee('Kerala Traders')->assertDontSee('Kerala website redesign');
    }
}
