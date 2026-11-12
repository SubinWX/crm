<?php

namespace Tests\Feature;

use App\Livewire\Users\Index as UsersIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertSame(['clients.view', 'clients.manage', 'tasks.manage', 'posters.manage', 'sales.manage'], $admin->assignablePermissions('staff'));
        $this->assertSame(['clients.view', 'clients.manage', 'tasks.manage', 'posters.manage'], $plainAdmin->assignablePermissions('staff'));
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
        $this->assertSame(['staff', ['clients.view', 'sales.manage']], [$hire->role, $hire->permissions]);
        // The role cannot be pushed to Admin or Super Admin from the Staff page.
        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('create')->set('form.name', 'Sneaky')->set('form.email', 'sneaky@example.com')
            ->set('form.role', 'super_admin')->call('save')->assertHasErrors('form.role');
        $this->assertNull(User::where('email', 'sneaky@example.com')->first());

        // Editing staff: the Admin changes what is theirs to change; access a Super Admin gave (Finance) is left as it was.
        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('edit', $staff->id)->assertSet('form.permissions', ['clients.view', 'finance.manage'])
            ->set('form.permissions', ['tasks.manage'])->call('save')->assertHasNoErrors();
        $this->assertSame(['tasks.manage', 'finance.manage'], $staff->fresh()->permissions);
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
}
