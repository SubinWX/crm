<?php

namespace Tests\Feature;

use App\Livewire\Portal\TicketCreate;
use App\Livewire\Portal\Tickets as PortalTickets;
use App\Livewire\Portal\TicketShow as PortalTicketShow;
use App\Livewire\Support\PortalUsers;
use App\Livewire\Support\Tickets as DeskTickets;
use App\Livewire\Support\TicketShow as DeskTicketShow;
use App\Models\Client;
use App\Models\PortalUser;
use App\Models\SupportAttachment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\Portal\PortalResetPassword;
use App\Notifications\Portal\PortalVerifyEmail;
use App\Notifications\Portal\TicketUpdateForCustomer;
use App\Notifications\SupportTicketActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Livewire;
use Tests\TestCase;

class SupportPortalTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected User $agent;

    protected User $plainStaff;

    protected PortalUser $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        $this->admin = User::factory()->superAdmin()->create(['name' => 'Asha Admin']);
        $this->agent = User::factory()->staff(['support.manage'])->create(['name' => 'Sunil Support']);
        $this->plainStaff = User::factory()->staff(['clients.view'])->create();
        $this->customer = PortalUser::create(['name' => 'Carol Customer', 'email' => 'carol@client.test', 'password' => 'portal-pass-1', 'email_verified_at' => now()]);
    }

    protected function submitTicket(PortalUser $user, array $overrides = []): SupportTicket
    {
        $component = Livewire::actingAs($user, 'portal')->test(TicketCreate::class)
            ->set('subject', $overrides['subject'] ?? 'Contact form not sending')
            ->set('description', $overrides['description'] ?? 'The contact form on /contact shows an error after submit.');
        foreach (array_diff_key($overrides, array_flip(['subject', 'description'])) as $key => $value) {
            $component->set($key, $value);
        }
        $component->call('submit')->assertHasNoErrors()->assertRedirect();

        return SupportTicket::latest('id')->firstOrFail();
    }

    public function test_someone_can_register_sign_in_and_reset_their_portal_password(): void
    {
        $this->get(route('portal.home'))->assertOk()->assertSee('Support Portal')->assertSee('Create an account');
        $this->get(route('portal.tickets.index'))->assertRedirect(route('portal.login'));

        $this->post(route('portal.register'), [
            'name' => 'Nina New', 'email' => 'Nina@Client.test', 'company' => 'Nina Bakes',
            'password' => 'portal-pass-9', 'password_confirmation' => 'portal-pass-9',
        ])->assertRedirect(route('portal.tickets.index'));

        $nina = PortalUser::where('email', 'nina@client.test')->firstOrFail();
        $this->assertAuthenticatedAs($nina, 'portal');
        $this->assertGuest('web'); // a portal login is never a team login

        // New sign-ups must confirm their email before they can use tickets.
        $this->assertNull($nina->email_verified_at);
        Notification::assertSentTo($nina, PortalVerifyEmail::class);
        $this->get(route('portal.tickets.index'))->assertRedirect(route('portal.verify.notice'));
        $this->get(route('portal.tickets.create'))->assertRedirect(route('portal.verify.notice'));
        $this->get(route('portal.verify.notice'))->assertOk()->assertSee('Confirm your email address');
        Livewire::actingAs($nina, 'portal')->test(TicketCreate::class)->assertForbidden();
        $this->post(route('portal.verify.resend'))->assertSessionHas('status');
        // A link without a valid signature does nothing; the emailed link confirms the address.
        $this->get(route('portal.verify', ['id' => $nina->id, 'hash' => sha1($nina->email)]))->assertForbidden();
        $this->assertNull($nina->fresh()->email_verified_at);
        $this->get(PortalVerifyEmail::url($nina))->assertRedirect(route('portal.tickets.index'));
        $this->assertNotNull($nina->fresh()->email_verified_at);
        $this->actingAs($nina->fresh(), 'portal')->get(route('portal.tickets.index'))->assertOk()->assertSee('My tickets');

        $this->post(route('portal.logout'))->assertRedirect(route('portal.home'));
        $this->assertGuest('portal');

        // Duplicate email and bots (honeypot) do not create accounts.
        $this->post(route('portal.register'), ['name' => 'X', 'email' => 'nina@client.test', 'password' => 'portal-pass-9', 'password_confirmation' => 'portal-pass-9'])->assertSessionHasErrors('email');
        $this->post(route('portal.register'), ['name' => 'Bot', 'email' => 'bot@spam.test', 'website' => 'http://spam', 'password' => 'portal-pass-9', 'password_confirmation' => 'portal-pass-9']);
        $this->assertDatabaseMissing('portal_users', ['email' => 'bot@spam.test']);

        $this->post(route('portal.login'), ['email' => 'nina@client.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post(route('portal.login'), ['email' => 'nina@client.test', 'password' => 'portal-pass-9'])->assertRedirect(route('portal.tickets.index'));
        $this->post(route('portal.logout'));

        $this->post(route('portal.password.email'), ['email' => 'nina@client.test'])->assertSessionHas('status');
        Notification::assertSentTo($nina, PortalResetPassword::class);

        // Team credentials do not work on the portal, and portal credentials do not work on the team login.
        $this->post(route('portal.login'), ['email' => $this->admin->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post(route('login'), ['email' => 'nina@client.test', 'password' => 'portal-pass-9'])->assertSessionHasErrors('email');
    }

    public function test_customer_submits_a_ticket_and_gets_a_ticket_number(): void
    {
        $ticket = $this->submitTicket($this->customer, [
            'type' => 'support', 'priority' => 'high', 'related_work' => 'Company website',
            'files' => [UploadedFile::fake()->image('screenshot.png', 600, 400)],
        ]);

        $this->assertMatchesRegularExpression('/^SUP-\d{4}-0001$/', $ticket->number);
        $this->assertSame('open', $ticket->status);
        $this->assertSame('high', $ticket->priority);
        $this->assertSame($this->customer->id, $ticket->portal_user_id);
        $this->assertSame(1, $ticket->messages()->count());
        $attachment = SupportAttachment::firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        // Team is told (Super Admins + Staff who answer tickets), the customer gets a confirmation.
        Notification::assertSentTo([$this->admin, $this->agent], SupportTicketActivity::class);
        Notification::assertNotSentTo($this->plainStaff, SupportTicketActivity::class);
        Notification::assertSentTo($this->customer, TicketUpdateForCustomer::class, fn ($n) => $n->event === 'received'
            && str_contains($n->toMail($this->customer)->subject, $ticket->number));

        // Numbers keep counting; feedback carries a rating; new work is its own type.
        $feedback = $this->submitTicket($this->customer, ['type' => 'feedback', 'rating' => '5', 'subject' => 'Great launch', 'description' => 'The launch went really smoothly, thank you.']);
        $this->assertStringEndsWith('-0002', $feedback->number);
        $this->assertSame(5, $feedback->rating);
        $this->assertStringEndsWith('-0003', $this->submitTicket($this->customer, ['type' => 'new_work', 'subject' => 'Booking page'])->number);

        // Validation and bad uploads.
        Livewire::actingAs($this->customer, 'portal')->test(TicketCreate::class)->call('submit')->assertHasErrors(['subject', 'description']);
        Livewire::actingAs($this->customer, 'portal')->test(TicketCreate::class)
            ->set('subject', 'Bad file')->set('description', 'Trying to upload a script file here.')
            ->set('files', [UploadedFile::fake()->create('run.php', 5, 'application/x-php')])
            ->call('submit')->assertHasErrors('files.0');

        // The customer sees their tickets and can open one by number.
        $this->actingAs($this->customer, 'portal')->get(route('portal.tickets.index'))->assertOk()->assertSee($ticket->number)->assertSee('Great launch');
        $this->actingAs($this->customer, 'portal')->get(route('portal.tickets.show', $ticket->number))->assertOk()->assertSee('Contact form not sending')->assertSee('screenshot.png');
        $this->actingAs($this->customer, 'portal')->get(route('portal.attachments', $attachment))->assertOk();
    }

    public function test_tickets_are_private_to_their_owner_and_the_portal_is_separate_from_the_crm(): void
    {
        $ticket = $this->submitTicket($this->customer, ['files' => [UploadedFile::fake()->image('shot.png')]]);
        $attachment = SupportAttachment::firstOrFail();
        $other = PortalUser::create(['name' => 'Olly Other', 'email' => 'olly@else.test', 'password' => 'portal-pass-2', 'email_verified_at' => now()]);

        $this->actingAs($other, 'portal')->get(route('portal.tickets.show', $ticket->number))->assertNotFound();
        $this->actingAs($other, 'portal')->get(route('portal.attachments', $attachment))->assertNotFound();
        $this->actingAs($other, 'portal')->get(route('portal.tickets.index'))->assertOk()->assertDontSee($ticket->number);
        Livewire::actingAs($other, 'portal')->test(PortalTickets::class)->set('search', 'SUP')->assertDontSee($ticket->number);

        // A portal account cannot open any team screen (it is not signed in on the team guard at all).
        foreach (['dashboard', 'clients.index', 'support.tickets.index', 'support.portal-users', 'invoices.index', 'users.index'] as $route) {
            $this->actingAs($other, 'portal')->get(route($route))->assertRedirect(route('login'));
        }
        $this->actingAs($other, 'portal')->get(route('support.attachments', $attachment))->assertRedirect(route('login'));

        // Disabled portal accounts are signed out.
        $other->update(['is_active' => false]);
        $this->actingAs($other, 'portal')->get(route('portal.tickets.index'))->assertRedirect(route('portal.login'));
    }

    public function test_team_desk_replies_notes_and_status_flow(): void
    {
        $ticket = $this->submitTicket($this->customer);

        // Who can use the desk.
        $this->actingAs($this->agent, 'web')->get(route('support.tickets.index'))->assertOk()->assertSee($ticket->number);
        $this->actingAs($this->agent, 'web')->get(route('support.tickets.show', $ticket))->assertOk()->assertSee('Carol Customer');
        $this->actingAs($this->plainStaff, 'web')->get(route('support.tickets.index'))->assertForbidden();
        Livewire::actingAs($this->plainStaff, 'web')->test(DeskTickets::class)->assertForbidden();
        Livewire::actingAs($this->plainStaff, 'web')->test(DeskTicketShow::class, ['ticket' => $ticket])->assertForbidden();
        $sub = User::factory()->create(['role' => User::ROLE_SUB_ADMIN, 'permissions' => null]);
        $this->actingAs($sub, 'web')->get(route('support.tickets.index'))->assertForbidden();

        // Internal note: customer is not emailed and never sees it.
        Livewire::actingAs($this->agent, 'web')->test(DeskTicketShow::class, ['ticket' => $ticket])
            ->set('internal', true)->set('reply', 'SECRET: check SMTP credentials first')
            ->set('files', [UploadedFile::fake()->create('internal.pdf', 20, 'application/pdf')])
            ->call('send')->assertHasNoErrors();
        Notification::assertNotSentTo($this->customer, TicketUpdateForCustomer::class, fn ($n) => $n->event === 'replied');
        $this->assertSame('open', $ticket->fresh()->status);
        $internalFile = SupportAttachment::where('name', 'internal.pdf')->firstOrFail();
        $this->actingAs($this->customer, 'portal')->get(route('portal.tickets.show', $ticket->number))->assertOk()->assertDontSee('SECRET')->assertDontSee('internal.pdf');
        $this->actingAs($this->customer, 'portal')->get(route('portal.attachments', $internalFile))->assertNotFound();
        $this->actingAs($this->agent, 'web')->get(route('support.attachments', $internalFile))->assertOk();

        // Public reply: assigns the ticket, sets the status, emails the customer.
        Livewire::actingAs($this->agent, 'web')->test(DeskTicketShow::class, ['ticket' => $ticket])
            ->set('reply', 'Fixed the mail settings, please try again.')->call('send')->assertHasNoErrors();
        $ticket->refresh();
        $this->assertSame('waiting', $ticket->status);
        $this->assertSame($this->agent->id, $ticket->assigned_to);
        Notification::assertSentTo($this->customer, TicketUpdateForCustomer::class, fn ($n) => $n->event === 'replied');
        $this->actingAs($this->customer, 'portal')->get(route('portal.tickets.show', $ticket->number))
            ->assertSee('Fixed the mail settings')->assertSee('Waiting for your reply');

        // Customer reply reopens it and tells the assignee only.
        Notification::fake();
        Livewire::actingAs($this->customer, 'portal')->test(PortalTicketShow::class, ['number' => $ticket->number])
            ->set('reply', 'Works now, thank you!')->call('send')->assertHasNoErrors();
        $this->assertSame('open', $ticket->fresh()->status);
        Notification::assertSentTo($this->agent, SupportTicketActivity::class, fn ($n) => $n->event === 'customer_replied');
        Notification::assertNotSentTo($this->admin, SupportTicketActivity::class);

        // Either side can resolve; a customer reply after that reopens.
        Livewire::actingAs($this->customer, 'portal')->test(PortalTicketShow::class, ['number' => $ticket->number])->call('markResolved');
        $this->assertSame('resolved', $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->closed_at);
        Livewire::actingAs($this->customer, 'portal')->test(PortalTicketShow::class, ['number' => $ticket->number])
            ->set('reply', 'It broke again')->call('send')->assertHasNoErrors();
        $this->assertSame('open', $ticket->fresh()->status);

        // Desk controls: priority, assignee, link to a CRM client.
        $client = Client::create(['name' => 'Carol Co']);
        Livewire::actingAs($this->admin, 'web')->test(DeskTicketShow::class, ['ticket' => $ticket])
            ->call('setPriority', 'urgent')->call('assign', (string) $this->admin->id)->call('linkClient', (string) $client->id)->call('setStatus', 'closed');
        $ticket->refresh();
        $this->assertSame(['urgent', $this->admin->id, $client->id, 'closed'], [$ticket->priority, $ticket->assigned_to, $ticket->client_id, $ticket->status]);
        $this->assertSame($client->id, $this->customer->fresh()->client_id);
        Livewire::actingAs($this->admin, 'web')->test(DeskTicketShow::class, ['ticket' => $ticket])->call('assign', (string) $this->plainStaff->id)->assertStatus(422);
    }

    public function test_google_sign_in_creates_or_links_a_portal_account(): void
    {
        // Hidden and unreachable until Google keys are configured.
        $this->get(route('portal.login'))->assertDontSee('Continue with Google');
        $this->get(route('portal.google'))->assertNotFound();

        config(['services.google.client_id' => 'test-id', 'services.google.client_secret' => 'test-secret']);
        $this->get(route('portal.login'))->assertSee('Continue with Google');

        $fake = function (string $id, string $email, string $name) {
            $user = (new GoogleUser)->map(['id' => $id, 'name' => $name, 'email' => $email]);
            $user->user = ['email_verified' => true];
            Socialite::shouldReceive('driver->user')->once()->andReturn($user);
        };

        // New person → account created without a password.
        $fake('g-111', 'Gita@Gmail.test', 'Gita Google');
        $this->get(route('portal.google.callback'))->assertRedirect(route('portal.tickets.index'));
        $gita = PortalUser::where('email', 'gita@gmail.test')->firstOrFail();
        $this->assertSame('g-111', $gita->google_id);
        $this->assertNull($gita->password);
        $this->assertAuthenticatedAs($gita, 'portal');
        $this->post(route('portal.logout'));

        // Existing email account → linked, not duplicated.
        $fake('g-222', 'carol@client.test', 'Carol C');
        $this->get(route('portal.google.callback'))->assertRedirect(route('portal.tickets.index'));
        $this->assertSame('g-222', $this->customer->fresh()->google_id);
        $this->assertSame(2, PortalUser::count());
        $this->post(route('portal.logout'));

        // Disabled account → refused.
        $gita->update(['is_active' => false]);
        $fake('g-111', 'gita@gmail.test', 'Gita Google');
        $this->get(route('portal.google.callback'))->assertRedirect(route('portal.login'))->assertSessionHasErrors('email');
        $this->assertGuest('portal');
    }

    public function test_admins_and_sub_admins_manage_portal_users(): void
    {
        $sub = User::factory()->create(['role' => User::ROLE_SUB_ADMIN, 'permissions' => null]);
        $subClient = Client::create(['name' => 'Sub Client', 'created_by' => $sub->id]);
        $adminClient = Client::create(['name' => 'Admin Client', 'created_by' => $this->admin->id]);

        // Super Admin adds a portal user with a password; it works on the portal straight away.
        Livewire::actingAs($this->admin, 'web')->test(PortalUsers::class)
            ->call('create')->set('form.name', 'Paula Portal')->set('form.email', 'paula@client.test')
            ->set('form.client_id', (string) $adminClient->id)->set('form.password', 'portal-pass-7')
            ->call('save')->assertHasNoErrors()->assertSet('created.login_url', route('portal.login'))->assertDontSee('portal-pass-7');
        $paula = PortalUser::where('email', 'paula@client.test')->firstOrFail();
        $this->assertSame($this->admin->id, $paula->created_by);
        $this->post(route('portal.login'), ['email' => 'paula@client.test', 'password' => 'portal-pass-7'])->assertRedirect(route('portal.tickets.index'));
        // Her tickets are linked to the client automatically.
        $this->assertSame($adminClient->id, $this->submitTicket($paula)->client_id);

        // Sub Admin: can add, sees only the ones they added, can only link their own clients.
        $this->actingAs($sub, 'web')->get(route('support.portal-users'))->assertOk()->assertDontSee('paula@client.test')->assertDontSee('carol@client.test');
        Livewire::actingAs($sub, 'web')->test(PortalUsers::class)
            ->call('create')->set('form.name', 'Sam Sub Contact')->set('form.email', 'samc@client.test')
            ->set('form.client_id', (string) $adminClient->id)->set('form.password', 'portal-pass-8')
            ->call('save')->assertHasErrors('form.client_id');
        Livewire::actingAs($sub, 'web')->test(PortalUsers::class)
            ->call('create')->set('form.name', 'Sam Sub Contact')->set('form.email', 'samc@client.test')
            ->set('form.client_id', (string) $subClient->id)->set('form.password', 'portal-pass-8')
            ->call('save')->assertHasNoErrors();
        $this->actingAs($sub, 'web')->get(route('support.portal-users'))->assertSee('samc@client.test');
        Livewire::actingAs($sub, 'web')->test(PortalUsers::class)->call('edit', $paula->id)->assertNotFound();
        Livewire::actingAs($sub, 'web')->test(PortalUsers::class)->call('toggleActive', $paula->id)->assertNotFound();

        // Super Admin sees everyone, can reset a password and disable an account.
        $this->actingAs($this->admin, 'web')->get(route('support.portal-users'))->assertSee('paula@client.test')->assertSee('samc@client.test')->assertSee('carol@client.test');
        Livewire::actingAs($this->admin, 'web')->test(PortalUsers::class)->call('edit', $paula->id)->set('form.password', 'portal-pass-new')->call('save')->assertHasNoErrors();
        Livewire::actingAs($this->admin, 'web')->test(PortalUsers::class)->call('toggleActive', $paula->id);
        auth('portal')->logout();
        $this->post(route('portal.login'), ['email' => 'paula@client.test', 'password' => 'portal-pass-new'])->assertSessionHasErrors('email');

        // Staff do not manage portal users.
        $this->actingAs($this->agent, 'web')->get(route('support.portal-users'))->assertForbidden();
        Livewire::actingAs($this->plainStaff, 'web')->test(PortalUsers::class)->assertForbidden();
    }
}
