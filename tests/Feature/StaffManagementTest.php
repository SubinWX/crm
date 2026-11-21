<?php

namespace Tests\Feature;

use App\Livewire\Office\BankDetails;
use App\Livewire\Users\Index as UsersIndex;
use App\Livewire\Work\Projects;
use App\Livewire\Work\Renewals;
use App\Models\ActivityLog;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admins_manage_staff_and_only_super_admins_manage_admins(): void
    {
        $super = User::factory()->superAdmin()->create(['name' => 'Asha Super']);
        // An Admin who was given Sales by a Super Admin, and one who was given nothing extra.
        $admin = User::factory()->create(['name' => 'Anil Admin', 'role' => User::ROLE_SUB_ADMIN, 'permissions' => ['sales.manage']]);
        $plainAdmin = User::factory()->create(['role' => User::ROLE_SUB_ADMIN, 'permissions' => []]);
        $staff = User::factory()->create(['name' => 'Sita Staff', 'role' => User::ROLE_STAFF, 'permissions' => ['clients.view', 'finance.manage']]);

        // The middle role is shown as "Admin".
        $this->assertSame(['Super Admin', 'Admin', 'Staff'], array_values(User::ROLES));

        // What each may hand to staff: everyday areas always; a guarded area only if they hold it themselves.
        $this->assertSame(array_keys(User::STAFF_PERMISSIONS), $super->assignablePermissions('staff'));
        // Clients, projects and hosting are for Super Admins to give: an Admin only sees the clients they added.
        $everyday = ['tasks.manage', 'posters.manage'];
        $this->assertSame([...$everyday, 'sales.manage'], $admin->assignablePermissions('staff'));
        $this->assertSame($everyday, $plainAdmin->assignablePermissions('staff'));
        $this->assertSame([], $admin->assignablePermissions('sub_admin'));
        $this->assertSame([], $staff->assignablePermissions('staff'));

        // Pages: Admins get the Staff page, not the Admins page; staff get neither.
        $this->actingAs($admin)->get(route('staff.index'))->assertOk()->assertSee('Add staff')->assertSee('Sita Staff')->assertDontSee('Asha Super')->assertDontSee('Two-step login for Super Admins');
        $this->actingAs($admin)->get(route('users.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee(route('staff.index'), false)->assertDontSee(route('users.index'), false);
        $this->flushSession()->actingAs($staff)->get(route('staff.index'))->assertForbidden();
        Livewire::actingAs($staff)->test(UsersIndex::class, ['scope' => 'staff'])->assertForbidden();
        Livewire::actingAs($admin)->test(UsersIndex::class)->assertForbidden();
        $this->flushSession()->actingAs($super)->get(route('users.index', ['role' => 'sub_admin']))->assertOk()->assertSee('Add admin')->assertSee('Anil Admin')->assertDontSee('Sita Staff')->assertSee('Staff accounts (1)');
        $this->actingAs($super)->get(route('staff.index'))->assertOk()->assertSee('Sita Staff');

        // An Admin adds a staff member. A guarded area they do not hold, or a made-up one, is not saved even if it is sent.
        $page = Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('create')->assertSee('(Super Admin only)')
            ->set('form.name', 'New Hire')->set('form.email', 'hire@example.com')->set('form.password', 'secret-pass-1')
            ->set('form.permissions', ['clients.view', 'sales.manage', 'finance.manage', 'team.manage'])->call('save')->assertHasNoErrors();
        $hire = User::where('email', 'hire@example.com')->firstOrFail();
        // Client access is not an Admin's to give, so it is dropped along with the areas the Admin does not hold.
        $this->assertSame(['staff', ['sales.manage']], [$hire->role, $hire->permissions]);
        // The role cannot be pushed to Admin or Super Admin from the Staff page.
        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('create')->set('form.name', 'Sneaky')->set('form.email', 'sneaky@example.com')
            ->set('form.role', 'super_admin')->call('save')->assertHasErrors('form.role');
        $this->assertNull(User::where('email', 'sneaky@example.com')->first());

        // Editing staff: the Admin changes what is theirs to change; access a Super Admin gave (Finance) is left as it was.
        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('edit', $staff->id)->assertSet('form.permissions', ['clients.view', 'finance.manage'])
            ->set('form.permissions', ['tasks.manage'])->call('save')->assertHasNoErrors();
        // Client access and Finance were given by a Super Admin, so the Admin can neither add nor remove them.
        $this->assertSame(['clients.view', 'tasks.manage', 'finance.manage'], $staff->fresh()->permissions);
        // An Admin can deactivate and reactivate staff, and set a password.
        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('toggleActive', $staff->id);
        $this->assertFalse($staff->fresh()->is_active);
        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('toggleActive', $staff->id);

        // An Admin cannot touch Admin or Super Admin accounts through the Staff page.
        foreach ([$super, $plainAdmin] as $protected) {
            Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('edit', $protected->id)->assertForbidden();
            Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('toggleActive', $protected->id)->assertForbidden();
            Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('resetTwoFactor', $protected->id)->assertForbidden();
        }
        $this->assertTrue($super->fresh()->is_active && $plainAdmin->fresh()->is_active);
        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('setRequireTwoFactor', true)->assertForbidden();

        // Only a Super Admin adds Admins and chooses their areas; staff are not edited from the Admins page.
        Livewire::actingAs($super)->test(UsersIndex::class)->call('create')->set('form.name', 'Second Admin')->set('form.email', 'second@example.com')->set('form.password', 'secret-pass-1')
            ->set('form.role', 'sub_admin')->set('form.permissions', ['careers.manage', 'finance.manage', 'tasks.manage'])->call('save')->assertHasNoErrors();
        $this->assertSame(['careers.manage', 'finance.manage'], User::where('email', 'second@example.com')->firstOrFail()->permissions);
        Livewire::actingAs($super)->test(UsersIndex::class)->call('edit', $staff->id)->assertForbidden();
        Livewire::actingAs($super)->test(UsersIndex::class)->call('create')->set('form.name', 'X')->set('form.email', 'x@example.com')->set('form.role', 'staff')->call('save')->assertHasErrors('form.role');
        // A Super Admin on the Staff page can give any area.
        Livewire::actingAs($super)->test(UsersIndex::class, ['scope' => 'staff'])->call('edit', $hire->id)->assertDontSee('(Super Admin only)')
            ->set('form.permissions', ['finance.manage', 'team.manage'])->call('save')->assertHasNoErrors();
        $this->assertSame(['finance.manage', 'team.manage'], $hire->fresh()->permissions);
    }

    public function test_clients_projects_and_hosting_are_given_separately(): void
    {
        $client = Client::create(['name' => 'Kerala Traders']);
        $project = Project::create(['client_id' => $client->id, 'name' => 'Website redesign', 'kind' => 'project', 'status' => 'active']);
        $make = fn (array $permissions) => User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => $permissions]);

        // Projects only: the project list and pages open; clients and hosting do not, and the client name is not a link.
        $projectsOnly = $make(['projects.view']);
        $this->actingAs($projectsOnly)->get('/projects')->assertOk()->assertSee('Website redesign')->assertSee('Kerala Traders')->assertDontSee('/clients/'.$client->id);
        $this->actingAs($projectsOnly)->get('/projects/'.$project->id)->assertOk();
        $this->actingAs($projectsOnly)->get('/clients')->assertForbidden();
        $this->actingAs($projectsOnly)->get('/clients/'.$client->id)->assertForbidden();
        $this->actingAs($projectsOnly)->get('/renewals')->assertForbidden();
        Livewire::actingAs($projectsOnly)->test(Projects::class)->call('create')->assertForbidden();
        // "Add and edit projects" includes seeing them.
        $projectsManager = $make(['projects.manage']);
        Livewire::actingAs($projectsManager)->test(Projects::class)->assertSee('Website redesign')->call('create')->assertHasNoErrors();

        // Hosting only.
        $hostingOnly = $make(['hosting.view']);
        $this->actingAs($hostingOnly)->get('/renewals')->assertOk();
        $this->actingAs($hostingOnly)->get('/projects')->assertForbidden();
        $this->actingAs($hostingOnly)->get('/clients')->assertForbidden();
        Livewire::actingAs($hostingOnly)->test(Renewals::class)->call('create')->assertForbidden();
        Livewire::actingAs($make(['hosting.manage']))->test(Renewals::class)->call('create')->assertHasNoErrors();

        // Clients only: the profile opens without the Projects and Hosting tabs.
        $clientsOnly = $make(['clients.view']);
        $this->actingAs($clientsOnly)->get('/clients/'.$client->id)->assertOk()->assertSee('Contacts')->assertDontSee('Hosting &amp; Renewals', false)->assertDontSee('>Projects</button>', false);
        $this->actingAs($clientsOnly)->get('/projects')->assertForbidden();
        $this->actingAs($clientsOnly)->get('/renewals')->assertForbidden();
        $this->actingAs($clientsOnly)->get('/clients/'.$client->id.'?tab=renewals')->assertOk()->assertDontSee('Add hosting');
        // All three together behave as before.
        $all = $make(['clients.view', 'projects.view', 'hosting.view']);
        $this->actingAs($all)->get('/clients/'.$client->id)->assertOk()->assertSee('Hosting &amp; Renewals', false)->assertSee('Projects');
        $this->actingAs($all)->get('/projects')->assertOk()->assertSee('/clients/'.$client->id);

        // People who had the old combined options keep the same reach after the change.
        $old = $make(['clients.view', 'tasks.manage']);
        $oldManager = $make(['clients.manage']);
        (require database_path('migrations/2026_11_13_180000_split_client_access_into_clients_projects_hosting.php'))->up();
        $this->assertSame(['clients.view', 'tasks.manage', 'projects.view', 'hosting.view'], $old->fresh()->permissions);
        $this->assertEqualsCanonicalizing(['clients.manage', 'clients.view', 'projects.view', 'projects.manage', 'hosting.view', 'hosting.manage'], $oldManager->fresh()->permissions);
        $this->assertSame(['projects.view'], $projectsOnly->fresh()->permissions);

        // The form shows each area as its own switch.
        $super = User::factory()->superAdmin()->create();
        Livewire::actingAs($super)->test(UsersIndex::class, ['scope' => 'staff'])->call('create')
            ->assertSee('View projects')->assertSee('Add and edit hosting and renewals')->assertSee('View clients')->assertSeeHtml('role="switch"')->assertSee('All on');
    }

    public function test_people_have_a_job_role_that_can_be_chosen_added_and_changed(): void
    {
        $super = User::factory()->superAdmin()->create(['name' => 'Asha Super']);
        $admin = User::factory()->create(['role' => User::ROLE_SUB_ADMIN]);
        $role = fn (string $name) => Lookup::where('type', 'job_role')->where('name', $name)->first();
        $this->assertNotNull($role('Developer'));
        $this->assertNotNull($role('Team Lead'));

        // Choosing one from the list when adding a person.
        Livewire::actingAs($super)->test(UsersIndex::class, ['scope' => 'staff'])->call('create')->assertSee('Job role')->assertSee('Team Lead')->assertSee('Or add a new job role')
            ->set('form.name', 'Dev Person')->set('form.email', 'dev@example.com')->set('form.password', 'secret-pass-1')
            ->set('form.job_role_id', '999999')->call('save')->assertHasErrors('form.job_role_id')
            ->set('form.job_role_id', (string) $role('Developer')->id)->call('save')->assertHasNoErrors();
        $dev = User::where('email', 'dev@example.com')->firstOrFail();
        $this->assertSame(['Developer', 'Developer', User::ROLE_STAFF], [$dev->jobRole->name, $dev->title_label, $dev->role]);
        // It shows in the list and on the profile, next to the access level.
        Livewire::actingAs($super)->test(UsersIndex::class, ['scope' => 'staff'])->assertSee('Dev Person')->assertSee('Developer');
        $this->actingAs($super)->get('/profile/'.$dev->id)->assertOk()->assertSee('Job role')->assertSee('Developer')->assertSee('Access level');

        // Changing it, and typing one that is not in the list yet (added once, reused after that).
        Livewire::actingAs($super)->test(UsersIndex::class, ['scope' => 'staff'])->call('edit', $dev->id)->assertSet('form.job_role_id', (string) $role('Developer')->id)
            ->set('form.new_job_role', 'SEO Specialist')->call('save')->assertHasNoErrors();
        $this->assertSame('SEO Specialist', $dev->fresh()->jobRole->name);
        Livewire::actingAs($super)->test(UsersIndex::class, ['scope' => 'staff'])->call('create')->set('form.name', 'Second')->set('form.email', 'second-seo@example.com')
            ->set('form.password', 'secret-pass-1')->set('form.new_job_role', 'seo specialist')->call('save')->assertHasNoErrors();
        $this->assertSame(1, Lookup::where('type', 'job_role')->whereRaw('lower(name) = ?', ['seo specialist'])->count());
        // Clearing it.
        Livewire::actingAs($super)->test(UsersIndex::class, ['scope' => 'staff'])->call('edit', $dev->id)->set('form.job_role_id', '')->call('save')->assertHasNoErrors();
        $this->assertSame([null, 'Staff'], [$dev->fresh()->job_role_id, $dev->fresh()->title_label]);

        // An Admin picks from the list but cannot add to it; a job role never changes what someone can open.
        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('edit', $dev->id)->assertDontSee('Or add a new job role')
            ->set('form.new_job_role', 'Made Up Role')->set('form.job_role_id', (string) $role('Manager')->id)->call('save')->assertHasNoErrors();
        $this->assertSame('Manager', $dev->fresh()->jobRole->name);
        $this->assertNull($role('Made Up Role'));
        $this->assertFalse($dev->fresh()->can('team.manage'));
        // The list itself is kept in Settings.
        $this->actingAs($super)->get('/settings?tab=job_role')->assertOk()->assertSee('Job roles (people)')->assertSee('Team Lead');
    }

    public function test_bank_details_are_kept_and_copied_by_admins_and_super_admins_only(): void
    {
        $super = User::factory()->superAdmin()->create(['name' => 'Asha Super']);
        $admin = User::factory()->create(['name' => 'Sam Admin', 'role' => User::ROLE_SUB_ADMIN]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => ['finance.manage', 'clients.manage']]);

        // Staff cannot open it, whatever access they have.
        $this->actingAs($staff)->get('/bank-details')->assertForbidden();
        Livewire::actingAs($staff)->test(BankDetails::class)->assertForbidden();
        $this->actingAs($admin)->get('/bank-details')->assertOk()->assertSee('No bank details yet');

        // An Admin adds an account; the number and codes are tidied and checked.
        $page = Livewire::actingAs($admin)->test(BankDetails::class)->call('edit')->call('save')
            ->assertHasErrors(['form.label', 'form.account_name', 'form.bank_name', 'form.account_number']);
        $page->set('form.label', 'Main current account')->set('form.account_name', 'WX Partners LLP')->set('form.bank_name', 'HDFC Bank')
            ->set('form.account_number', '5020 0012 3456 78')->set('form.ifsc', 'hdfc123')->set('form.upi_id', 'not a upi')->call('save')->assertHasErrors(['form.ifsc', 'form.upi_id'])
            ->set('form.ifsc', 'hdfc0001234')->set('form.upi_id', 'wxpartners@hdfcbank')->set('form.branch', 'Kozhikode Main')->set('form.notes', 'Internal note')
            ->set('form.updated_by', $super->id)->call('save')->assertHasNoErrors();
        $account = BankAccount::firstOrFail();
        $this->assertSame(['50200012345678', 'HDFC0001234', true, $admin->id], [$account->account_number, $account->ifsc, $account->is_default, $account->updated_by]);
        // The account number is not readable in the database itself.
        $this->assertStringNotContainsString('50200012345678', (string) DB::table('bank_accounts')->value('account_number'));

        // The page shows every detail and one block with all of them, without the internal note.
        $this->assertSame("Account name: WX Partners LLP\nBank: HDFC Bank\nAccount number: 50200012345678\nIFSC code: HDFC0001234\nBranch: Kozhikode Main\nAccount type: Current\nUPI ID: wxpartners@hdfcbank", $account->asText());
        $this->actingAs($super)->get('/bank-details')->assertOk()->assertSee('50200012345678')->assertSee('Copy all')->assertSee('All details, ready to paste')->assertSee('Default');

        // A second account can take over as the default; there is always exactly one.
        Livewire::actingAs($super)->test(BankDetails::class)->call('edit')->set('form.label', 'Savings')->set('form.account_name', 'WX Partners LLP')
            ->set('form.bank_name', 'SBI')->set('form.account_number', '30001234567')->set('form.is_default', true)->call('save')->assertHasNoErrors();
        $this->assertSame(['Savings'], BankAccount::where('is_default', true)->pluck('label')->all());

        // Changes are written to the activity log. Only a Super Admin deletes.
        $this->assertTrue(ActivityLog::where('description', 'like', '%Bank details “Main current account” added by Sam Admin%')->exists());
        Livewire::actingAs($admin)->test(BankDetails::class)->call('delete', $account->id)->assertForbidden();
        Livewire::actingAs($super)->test(BankDetails::class)->call('delete', BankAccount::where('label', 'Savings')->value('id'));
        $this->assertSame(['Main current account', true], [BankAccount::sole()->label, BankAccount::sole()->is_default]);
    }
}
