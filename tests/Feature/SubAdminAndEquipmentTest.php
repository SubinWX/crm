<?php

namespace Tests\Feature;

use App\Livewire\Clients\Files as ClientFiles;
use App\Livewire\Clients\Form as ClientForm;
use App\Livewire\Clients\Index as ClientsIndex;
use App\Livewire\Clients\Show as ClientShow;
use App\Livewire\Equipment\Index as EquipmentIndex;
use App\Livewire\Hosting\Index as HostingIndex;
use App\Livewire\Posters\Index as PostersIndex;
use App\Livewire\Tasks\Index as TasksIndex;
use App\Livewire\Users\Index as UsersIndex;
use App\Models\Client;
use App\Models\ClientFile;
use App\Models\Equipment;
use App\Models\Lookup;
use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SubAdminAndEquipmentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected User $sub;

    protected Client $theirs;

    protected Client $other;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->superAdmin()->create();
        $this->sub = User::factory()->create(['role' => User::ROLE_SUB_ADMIN, 'permissions' => null, 'name' => 'Sam Sub']);
        $this->theirs = Client::create(['name' => 'Mine Ltd', 'created_by' => $this->sub->id]);
        $this->other = Client::create(['name' => 'Someone Elses Co', 'created_by' => $this->admin->id]);
    }

    public function test_sub_admin_can_add_clients_and_sees_only_their_own(): void
    {
        // Adds a client; Super Admins are still notified.
        Livewire::actingAs($this->sub)->test(ClientForm::class)
            ->set('form.name', 'Fresh Client')->call('save')->assertHasNoErrors()->assertRedirect();
        $fresh = Client::where('name', 'Fresh Client')->firstOrFail();
        $this->assertSame($this->sub->id, $fresh->created_by);
        $this->assertSame(1, $this->admin->notifications()->count());

        // List shows only their own.
        $this->actingAs($this->sub)->get(route('clients.index'))->assertOk()
            ->assertSee('Mine Ltd')->assertSee('Fresh Client')->assertDontSee('Someone Elses Co');
        Livewire::actingAs($this->sub)->test(ClientsIndex::class)->set('search', 'Someone')->assertDontSee('Someone Elses Co');

        // Own profile opens; another client's does not, by URL or by component.
        $this->actingAs($this->sub)->get(route('clients.show', $this->theirs))->assertOk()
            ->assertDontSee('Invoices &amp; Payments', false)->assertDontSee('Open tasks');
        $this->actingAs($this->sub)->get(route('clients.show', $this->other))->assertForbidden();
        Livewire::actingAs($this->sub)->test(ClientShow::class, ['client' => $this->other])->assertForbidden();
        Livewire::actingAs($this->sub)->test(HostingIndex::class, ['clientId' => $this->other->id])->assertForbidden();
        Livewire::actingAs($this->sub)->test(HostingIndex::class, ['clientId' => $this->theirs->id])->assertOk();
        Livewire::actingAs($this->sub)->test(ClientFiles::class, ['client' => $this->other])->assertForbidden();

        // Files and logo of another client are blocked too.
        Storage::disk('local')->put('clients/x/contract.pdf', 'pdf');
        Storage::disk('local')->put('logos/o.png', 'png');
        $this->other->update(['logo_path' => 'logos/o.png']);
        $otherFile = ClientFile::create(['client_id' => $this->other->id, 'name' => 'contract.pdf', 'path' => 'clients/x/contract.pdf']);
        $ownFile = ClientFile::create(['client_id' => $this->theirs->id, 'name' => 'contract.pdf', 'path' => 'clients/x/contract.pdf']);
        $this->actingAs($this->sub)->get(route('files.client', $otherFile))->assertForbidden();
        $this->actingAs($this->sub)->get(route('clients.logo', $this->other))->assertForbidden();
        $this->actingAs($this->sub)->get(route('files.client', $ownFile))->assertOk();

        // They can edit the clients they added (details, hosting, file uploads) but nobody else's.
        $this->actingAs($this->sub)->get(route('clients.edit', $this->theirs))->assertOk();
        Livewire::actingAs($this->sub)->test(ClientForm::class, ['client' => $this->theirs])
            ->set('form.contact_name', 'Updated Contact')->call('save')->assertHasNoErrors();
        $this->assertSame('Updated Contact', $this->theirs->fresh()->contact_name);
        Livewire::actingAs($this->sub)->test(HostingIndex::class, ['clientId' => $this->theirs->id])->call('create')->assertOk();
        $this->actingAs($this->sub)->get(route('clients.edit', $this->other))->assertForbidden();
        Livewire::actingAs($this->sub)->test(ClientForm::class, ['client' => $this->other])->assertForbidden();

        // Archiving and deleting stay with the team.
        Livewire::actingAs($this->sub)->test(ClientsIndex::class)->call('archive', $this->theirs->id)->assertForbidden();
        Livewire::actingAs($this->sub)->test(ClientShow::class, ['client' => $this->theirs])->call('toggleArchive')->assertForbidden();
    }

    public function test_sub_admin_is_kept_out_of_everything_else(): void
    {
        foreach (['renewals.index', 'tasks.index', 'posters.index', 'invoices.index', 'payments.index', 'expenses.index', 'invoices.export', 'users.index', 'settings.index', 'activity.index'] as $route) {
            $this->actingAs($this->sub)->get(route($route))->assertForbidden();
        }
        Livewire::actingAs($this->sub)->test(TasksIndex::class)->assertForbidden();
        Livewire::actingAs($this->sub)->test(PostersIndex::class)->assertForbidden();
        Livewire::actingAs($this->sub)->test(HostingIndex::class)->assertForbidden();

        // Their dashboard only mentions their own clients.
        $this->actingAs($this->sub)->get(route('dashboard'))->assertOk()
            ->assertSee('Clients you added')->assertSee('Mine Ltd')->assertDontSee('Someone Elses Co')->assertDontSee('Hosting payments');

        // Staff and Super Admins are unaffected.
        $staff = User::factory()->staff(['clients.view'])->create();
        $this->actingAs($staff)->get(route('clients.show', $this->other))->assertOk();
        $this->actingAs($staff)->get(route('tasks.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('clients.index'))->assertSee('Mine Ltd')->assertSee('Someone Elses Co');
    }

    public function test_super_admin_can_create_users_with_a_password_and_update_it_later(): void
    {
        Notification::fake();

        // Create a Sub Admin with a password: usable immediately, no invitation email.
        Livewire::actingAs($this->admin)->test(UsersIndex::class)
            ->call('create')
            ->set('form.name', 'Priya Partner')->set('form.email', 'priya@agency.test')
            ->set('form.role', 'sub_admin')->set('form.password', 'first-pass-123')
            ->call('save')->assertHasNoErrors()
            ->assertSet('created.login_url', route('login'))
            ->assertSet('created.invite_url', null)
            ->assertSee(route('login'))
            ->assertDontSee('first-pass-123');

        $user = User::where('email', 'priya@agency.test')->firstOrFail();
        $this->assertTrue($user->isSubAdmin());
        $this->assertFalse($user->hasPendingInvitation());
        Notification::assertNothingSentTo($user);

        auth()->logout();
        $this->post(route('login'), ['email' => 'priya@agency.test', 'password' => 'first-pass-123'])->assertRedirect(route('dashboard'));
        auth()->logout();

        // Update the password later; the old one stops working.
        Livewire::actingAs($this->admin)->test(UsersIndex::class)
            ->call('edit', $user->id)->set('form.password', 'second-pass-456')->call('save')->assertHasNoErrors();
        $this->assertTrue(Hash::check('second-pass-456', $user->fresh()->password));
        $this->assertFalse(Hash::check('first-pass-123', $user->fresh()->password));

        // Saving without a password leaves it alone; too-short passwords are rejected.
        Livewire::actingAs($this->admin)->test(UsersIndex::class)
            ->call('edit', $user->id)->set('form.phone', '12345')->call('save')->assertHasNoErrors();
        $this->assertTrue(Hash::check('second-pass-456', $user->fresh()->password));
        Livewire::actingAs($this->admin)->test(UsersIndex::class)
            ->call('edit', $user->id)->set('form.password', 'short')->call('save')->assertHasErrors('form.password');

        // No password → invitation email, and the invitation link is shown once to the admin.
        $component = Livewire::actingAs($this->admin)->test(UsersIndex::class, ['scope' => 'staff'])
            ->call('create')->set('form.name', 'Invited Person')->set('form.email', 'invited@agency.test')
            ->set('form.role', 'staff')->call('save')->assertHasNoErrors();
        $invited = User::where('email', 'invited@agency.test')->firstOrFail();
        Notification::assertSentTo($invited, UserInvited::class);
        $inviteUrl = $component->get('created.invite_url');
        $this->assertStringContainsString('/invitation/', $inviteUrl);
        auth()->logout();
        $this->get($inviteUrl)->assertOk()->assertSee('Invited Person');

        // A Super Admin changes their own password from the account menu, not here.
        Livewire::actingAs($this->admin)->test(UsersIndex::class)
            ->call('edit', $this->admin->id)->set('form.password', 'another-pass-789')->call('save')->assertHasErrors('form.password');
    }

    public function test_equipment_is_managed_by_super_admins_and_each_user_sees_only_their_own(): void
    {
        $staff = User::factory()->staff([])->create(['name' => 'Ravi Staff']);
        $laptop = Lookup::where('type', 'equipment_type')->where('name', 'Laptop')->value('id');

        Livewire::actingAs($this->admin)->test(EquipmentIndex::class)
            ->call('create')
            ->set('form.name', 'MacBook Air 13')->set('form.type_id', (string) $laptop)
            ->set('form.serial_number', 'C02XK1ABCD')->set('form.user_id', (string) $staff->id)
            ->set('form.purchase_cost', '95000')
            ->call('save')->assertHasNoErrors();
        Equipment::create(['name' => 'Dell Monitor 27', 'user_id' => $this->sub->id, 'serial_number' => 'DL-27-001']);
        Equipment::create(['name' => 'Spare Keyboard']);

        $item = Equipment::where('name', 'MacBook Air 13')->firstOrFail();
        $this->assertSame($staff->id, $item->user_id);
        $this->assertNotNull($item->assigned_on);

        // Super Admin sees everything and can filter per person.
        $this->actingAs($this->admin)->get(route('equipment.index'))->assertOk()
            ->assertSee('MacBook Air 13')->assertSee('Dell Monitor 27')->assertSee('Spare Keyboard');
        Livewire::actingAs($this->admin)->test(EquipmentIndex::class)->set('user', (string) $staff->id)
            ->assertSee('MacBook Air 13')->assertDontSee('Dell Monitor 27');
        Livewire::actingAs($this->admin)->test(EquipmentIndex::class)->set('user', 'unassigned')
            ->assertSee('Spare Keyboard')->assertDontSee('MacBook Air 13');

        // Reassign to someone else.
        Livewire::actingAs($this->admin)->test(EquipmentIndex::class)
            ->call('edit', $item->id)->set('form.user_id', (string) $this->sub->id)->call('save')->assertHasNoErrors();
        $this->assertSame($this->sub->id, $item->fresh()->user_id);

        // Everyone else: read-only, own items only, even if they tamper with the filter.
        $this->actingAs($this->sub)->get(route('equipment.index'))->assertOk()
            ->assertSee('My Equipment')->assertSee('MacBook Air 13')->assertSee('Dell Monitor 27')->assertDontSee('Spare Keyboard')->assertDontSee('Purchase cost');
        Livewire::actingAs($staff)->test(EquipmentIndex::class)->set('user', (string) $this->sub->id)->assertDontSee('Dell Monitor 27');
        Livewire::actingAs($staff)->test(EquipmentIndex::class)->call('create')->assertForbidden();
        Livewire::actingAs($staff)->test(EquipmentIndex::class)->call('delete', $item->id)->assertForbidden();
        $this->assertNotNull($item->fresh());

        // The Users screen links to each person's equipment.
        $this->actingAs($this->admin)->get(route('users.index', ['role' => 'sub_admin']))->assertOk()->assertSee('2 items');
    }
}
