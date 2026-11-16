<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsurePortalUserIsActive;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Careers\Apply;
use App\Livewire\Clients\Contacts;
use App\Livewire\Clients\Form as ClientForm;
use App\Livewire\EmailTools\Template;
use App\Livewire\Team\Leave;
use App\Livewire\Users\Index as UsersIndex;
use App\Models\Client;
use App\Models\EmailTemplate;
use App\Models\JobApplication;
use App\Models\LeaveRequest;
use App\Models\Lookup;
use App\Models\PortalUser;
use App\Models\User;
use App\Support\CalendarEvents;
use App\Support\OutboundUrl;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Tests\TestCase;

/** One test for each weakness found in the security review, so none of them can quietly come back. */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_every_page_sends_the_browser_protection_headers(): void
    {
        $response = $this->get('/login')->assertOk();
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));   // only on the live https site
    }

    public function test_a_deactivated_person_is_signed_out_everywhere_and_cut_off_from_open_pages(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 'def', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => time()]);

        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('toggleActive', $staff->id);
        $this->assertFalse($staff->fresh()->is_active);
        $this->assertSame(['def'], DB::table('sessions')->pluck('id')->all());

        // The "is this account still active" check also runs on Livewire actions, not only on page loads.
        $persistent = app(PersistentMiddleware::class)->getPersistentMiddleware();
        $this->assertContains(EnsureUserIsActive::class, $persistent);
        $this->assertContains(EnsurePortalUserIsActive::class, $persistent);
        $this->actingAs($staff->fresh())->get('/documents')->assertRedirect();
    }

    public function test_the_public_job_form_saves_only_its_own_fields_and_accepts_only_plain_names(): void
    {
        Storage::fake('local');
        Notification::fake();
        $details = ['form.name' => 'Anu Thomas', 'form.email' => 'anu@example.test', 'form.phone' => '9876543210', 'consent' => true];

        // A link written as a name would be clickable in the confirmation email.
        Livewire::test(Apply::class)->set($details)->set('form.name', '[Pay your invoice](https://evil.example)')
            ->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))->call('submit')->assertHasErrors('form.name');

        // Extra fields added by the browser never reach the record.
        Livewire::test(Apply::class)->set($details)->set('form.status', 'hired')->set('form.rating', 5)->set('form.notes', 'Forged internal note')
            ->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))->call('submit')->assertHasNoErrors();
        $application = JobApplication::firstOrFail();
        $this->assertSame(['Anu Thomas', 'new', null, null], [$application->name, $application->status, $application->rating, $application->notes]);

        // A second try with the same email gets the same thank-you and stores nothing.
        Livewire::test(Apply::class)->set($details)->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))->call('submit')->assertHasNoErrors()->assertSet('submitted', true);
        $this->assertSame(1, JobApplication::count());
    }

    public function test_google_sign_in_throws_away_a_password_set_by_whoever_registered_the_email_first(): void
    {
        config(['services.google.client_id' => 'test-id', 'services.google.client_secret' => 'test-secret']);
        // Someone registers with the victim's address and a password they know, and never verifies it.
        $account = PortalUser::create(['name' => 'Victim', 'email' => 'victim@gmail.test', 'password' => Hash::make('attacker-knows-this')]);
        $this->assertNull($account->email_verified_at);

        $google = (new GoogleUser)->map(['id' => 'g-999', 'name' => 'Victim', 'email' => 'victim@gmail.test']);
        $google->user = ['email_verified' => true];
        Socialite::shouldReceive('driver')->with('google')->andReturn(\Mockery::mock(['user' => $google]));
        $this->get(route('portal.google.callback'))->assertRedirect(route('portal.tickets.index'));

        $account->refresh();
        $this->assertSame('g-999', $account->google_id);
        $this->assertNotNull($account->email_verified_at);
        $this->assertFalse(Hash::check('attacker-knows-this', $account->password));
    }

    public function test_a_two_step_code_names_its_time_step_so_it_can_be_refused_the_second_time(): void
    {
        $secret = Totp::generateSecret();
        $now = 1_800_000_000;
        $code = Totp::codeAt($secret, $now);
        $this->assertSame(intdiv($now, 30), Totp::matchedStep($secret, $code, $now));
        $this->assertSame(intdiv($now, 30), Totp::matchedStep($secret, $code, $now + 30));   // still the same step a moment later
        $this->assertNull(Totp::matchedStep($secret, 'abcdef', $now));
    }

    public function test_provider_addresses_must_be_public_https_addresses(): void
    {
        foreach (['https://api.msg91.com/send', 'https://sms.example.test/api?x=1'] as $ok) {
            $this->assertTrue(OutboundUrl::isPublic($ok), $ok);
        }
        foreach (['http://api.example.test/send', 'https://localhost/x', 'https://127.0.0.1/x', 'https://10.0.0.5/x', 'https://192.168.1.10/x', 'https://169.254.169.254/latest/meta-data',
            'https://[::1]/x', 'https://2130706433/x', 'https://0x7f000001/x', 'https://printer.local/x', 'https://db.internal/x', 'ftp://example.test/x', 'not a url', ''] as $bad) {
            $this->assertFalse(OutboundUrl::isPublic($bad), $bad);
        }
    }

    public function test_client_and_contact_forms_ignore_fields_that_are_not_on_the_form(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $subAdmin = User::factory()->create(['role' => User::ROLE_SUB_ADMIN]);
        $mine = Client::create(['name' => 'My Client', 'created_by' => $subAdmin->id]);
        $other = Client::create(['name' => 'Someone Else’s Client', 'created_by' => $admin->id]);

        // An Admin editing their own client cannot point its logo at another stored file, or change who created it.
        Livewire::actingAs($subAdmin)->test(ClientForm::class, ['client' => $mine])
            ->set('form.logo_path', 'quotes/3/QUO-2026-0003-rev1.pdf')->set('form.created_by', $admin->id)->set('form.archived_at', '2020-01-01 00:00:00')
            ->assertSet('form.logo_path', 'quotes/3/QUO-2026-0003-rev1.pdf')   // the browser really can add the field; the save must drop it
            ->set('form.name', 'My Client Renamed')->call('save')->assertHasNoErrors();
        $mine->refresh();
        $this->assertSame(['My Client Renamed', null, $subAdmin->id, null], [$mine->name, $mine->logo_path, $mine->created_by, $mine->archived_at]);

        // Nor can they plant a contact on a client they cannot open.
        Livewire::actingAs($subAdmin)->test(Contacts::class, ['client' => $mine])->call('create')
            ->set('form.name', 'Planted Contact')->set('form.client_id', $other->id)->assertSet('form.client_id', $other->id)->call('save')->assertHasNoErrors();
        $this->assertSame([1, 0], [$mine->contacts()->where('name', 'Planted Contact')->count(), $other->contacts()->count()]);
    }

    public function test_leave_requests_of_other_people_cannot_be_read_by_changing_an_id(): void
    {
        $ravi = User::factory()->create(['name' => 'Ravi Staff', 'role' => User::ROLE_STAFF]);
        $meera = User::factory()->create(['name' => 'Meera Staff', 'role' => User::ROLE_STAFF]);
        $hr = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => ['team.manage']]);
        $type = Lookup::where('type', 'leave_type')->where('code', 'CL')->firstOrFail();
        $request = LeaveRequest::create(['user_id' => $meera->id, 'leave_type_id' => $type->id, 'from_date' => '2026-12-01', 'to_date' => '2026-12-01', 'days' => 1, 'day_part' => 'full', 'reason' => 'Private medical appointment', 'status' => 'pending']);

        Livewire::actingAs($ravi)->test(Leave::class)->set('decidingId', $request->id)->assertDontSee('Private medical appointment')->assertViewHas('deciding', null)
            ->set('balanceUserId', $meera->id)->assertViewHas('balanceUser', null);
        Livewire::actingAs($hr)->test(Leave::class)->set('decidingId', $request->id)->assertSee('Private medical appointment');
    }

    public function test_the_calendar_follows_the_separate_hosting_and_project_access(): void
    {
        $clientsOnly = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => ['clients.view']]);
        $all = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => ['clients.view', 'hosting.view', 'projects.view']]);

        $types = CalendarEvents::allowedTypes($clientsOnly);
        $this->assertContains('client', $types);
        $this->assertSame([], array_intersect(['hosting', 'renewal', 'project'], $types));
        $this->assertSame(['hosting', 'renewal', 'project'], array_values(array_intersect(['hosting', 'renewal', 'project'], CalendarEvents::allowedTypes($all))));
    }

    public function test_a_saved_email_template_is_not_overwritten_by_someone_else(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_STAFF]);
        $other = User::factory()->create(['role' => User::ROLE_STAFF]);
        $template = EmailTemplate::create(['name' => 'Welcome', 'data' => ['body' => '<div>Original</div>'], 'created_by' => $owner->id]);

        Livewire::actingAs($other)->test(Template::class)->call('load', $template->id)->set('name', 'Welcome')->set('form.body', '<div>Changed by someone else</div>')->call('save');
        $this->assertStringContainsString('Original', $template->fresh()->data['body']);
        $this->assertSame(2, EmailTemplate::count());   // their change was saved as their own copy
        $this->assertSame($other->id, EmailTemplate::latest('id')->first()->created_by);
    }

    public function test_an_admin_cannot_take_over_a_staff_account_that_has_access_the_admin_was_not_given(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_SUB_ADMIN, 'permissions' => []]);
        $finance = User::factory()->create(['role' => User::ROLE_STAFF, 'email' => 'finance@example.test', 'permissions' => ['clients.view', 'finance.manage'], 'password' => 'original-password']);
        $plain = User::factory()->create(['role' => User::ROLE_STAFF, 'email' => 'plain@example.test', 'permissions' => ['tasks.manage']]);
        $finance->forceFill(['two_factor_secret' => Totp::generateSecret(), 'two_factor_confirmed_at' => now()])->save();

        // Not the password, not the email, not the two-step login.
        $page = Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff']);
        $page->call('edit', $finance->id)->set('form.password', 'new-password-123')->call('save')->assertHasErrors('form.password');
        $page->call('edit', $finance->id)->set('form.email', 'mine@example.test')->call('save')->assertHasErrors('form.email');
        $page->call('resetTwoFactor', $finance->id)->assertForbidden();
        $finance->refresh();
        $this->assertTrue(Hash::check('original-password', $finance->password));
        $this->assertSame(['finance@example.test', true], [$finance->email, $finance->hasTwoFactor()]);

        // Other details of that person, and everything about a staff member within the Admin's reach, still work.
        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('edit', $finance->id)->set('form.phone', '9000000000')->call('save')->assertHasNoErrors();
        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('edit', $plain->id)->set('form.password', 'new-password-123')->call('save')->assertHasNoErrors();
        $this->assertTrue(Hash::check('new-password-123', $plain->fresh()->password));
        // An Admin cannot hand out access to every client, project or hosting record, even by sending the option from the browser.
        $this->assertSame([], array_intersect(User::SUPER_ADMIN_ONLY_PERMISSIONS, $admin->assignablePermissions('staff')));
        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('edit', $plain->id)->set('form.permissions', ['clients.view', 'clients.manage', 'hosting.view', 'tasks.manage'])->call('save')->assertHasNoErrors();
        $this->assertSame(['tasks.manage'], $plain->fresh()->permissions);
        // Nor take over a staff member who has it.
        $withClients = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => ['clients.view']]);
        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('edit', $withClients->id)->set('form.password', 'new-password-123')->call('save')->assertHasErrors('form.password');
        // A Super Admin is not restricted.
        Livewire::actingAs(User::factory()->superAdmin()->create())->test(UsersIndex::class, ['scope' => 'staff'])->call('resetTwoFactor', $finance->id)->assertHasNoErrors();
        $this->assertFalse($finance->fresh()->hasTwoFactor());
    }
}
