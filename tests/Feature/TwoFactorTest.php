<?php

namespace Tests\Feature;

use App\Livewire\Account\TwoFactor;
use App\Livewire\Users\Index as UsersIndex;
use App\Models\Setting;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function code(User $user): string
    {
        return Totp::codeAt($user->fresh()->two_factor_secret, now()->getTimestamp());
    }

    public function test_one_time_codes_follow_the_standard(): void
    {
        // Published test values from RFC 6238 (secret "12345678901234567890").
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame(['287082', '081804', '279037'], [Totp::codeAt($secret, 59), Totp::codeAt($secret, 1111111109), Totp::codeAt($secret, 2000000000)]);
        $this->assertTrue(Totp::verify($secret, '287 082', 59));
        $this->assertTrue(Totp::verify($secret, '287082', 59 + 30)); // the previous code is still accepted
        $this->assertFalse(Totp::verify($secret, '287082', 59 + 120));
        $this->assertFalse(Totp::verify($secret, 'abcdef', 59));
        $this->assertSame(32, strlen(Totp::generateSecret()));
        $this->assertStringContainsString('secret=ABC&issuer=Agency%20CRM', Totp::uri('ABC', 'a@b.example', 'Agency CRM'));
    }

    public function test_switching_it_on_and_signing_in_with_a_code_or_recovery_code(): void
    {
        $admin = User::factory()->superAdmin()->create(['email' => 'owner@example.com', 'password' => 'correct-horse-1']);

        // Setup: scan, confirm with a code, receive recovery codes once.
        $page = Livewire::actingAs($admin)->test(TwoFactor::class)->assertSee('Set up two-step login')->call('start')->assertSee('scan this code')->assertSee('<svg', false);
        $secret = session('two_factor.setup_secret');
        $page->set('code', '000000')->call('confirm')->assertHasErrors('code');
        $this->assertFalse($admin->fresh()->hasTwoFactor());
        $page->set('code', Totp::codeAt($secret, now()->getTimestamp()))->call('confirm')->assertHasNoErrors()->assertSee('Save these recovery codes now');
        $admin->refresh();
        $this->assertTrue($admin->hasTwoFactor());
        $this->assertCount(8, $admin->two_factor_recovery_codes);
        // Stored encrypted, and never included when the user is turned into an array.
        $this->assertStringNotContainsString($secret, (string) DB::table('users')->where('id', $admin->id)->value('two_factor_secret'));
        $this->assertArrayNotHasKey('two_factor_secret', $admin->toArray());

        // Signing in: the password alone no longer gets in.
        auth()->logout();
        $this->flushSession();
        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'correct-horse-1'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest('web');
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/two-step')->assertOk()->assertSee('Enter your 6-digit code');

        $this->post('/two-step', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest('web');
        $this->post('/two-step', ['code' => $this->code($admin)])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin, 'web');
        $this->get('/')->assertOk();

        // A recovery code works once.
        $this->post('/logout');
        $recovery = $admin->fresh()->two_factor_recovery_codes[0];
        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'correct-horse-1'])->assertRedirect(route('two-factor.challenge'));
        $this->get('/two-step?recovery=1')->assertOk()->assertSee('Enter a recovery code');
        $this->post('/two-step', ['recovery_code' => strtoupper($recovery)])->assertRedirect(route('dashboard'));
        $this->assertCount(7, $admin->fresh()->two_factor_recovery_codes);
        $this->post('/logout');
        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'correct-horse-1']);
        $this->post('/two-step', ['recovery_code' => $recovery])->assertSessionHasErrors('recovery_code');
        $this->assertGuest('web');

        // The code step cannot be reached without the password step, expires, and locks after repeated wrong codes.
        $this->flushSession();
        $this->get('/two-step')->assertRedirect(route('login'));
        $this->post('/two-step', ['code' => $this->code($admin)])->assertRedirect(route('login'));
        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'correct-horse-1']);
        $this->travel(11)->minutes();
        $this->post('/two-step', ['code' => $this->code($admin)])->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'correct-horse-1']);
        foreach (range(1, 5) as $i) {
            $this->post('/two-step', ['code' => '111111']);
        }
        $this->post('/two-step', ['code' => $this->code($admin)])->assertSessionHasErrors('code');
        $this->assertGuest('web');
    }

    public function test_recovery_codes_switching_off_and_admin_reset(): void
    {
        $admin = User::factory()->superAdmin()->create(['password' => 'correct-horse-1']);
        $other = User::factory()->superAdmin()->create();
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        foreach ([$admin, $staff] as $user) {
            $user->forceFill(['two_factor_secret' => Totp::generateSecret(), 'two_factor_recovery_codes' => Totp::recoveryCodes(), 'two_factor_confirmed_at' => now()])->save();
        }

        // New recovery codes and switching off both need the current password.
        $old = $admin->two_factor_recovery_codes;
        $page = Livewire::actingAs($admin)->test(TwoFactor::class)->assertSee('8')->set('password', 'nope')->call('regenerate')->assertHasErrors('password');
        $this->assertSame($old, $admin->fresh()->two_factor_recovery_codes);
        $page->set('password', 'correct-horse-1')->call('regenerate')->assertHasNoErrors()->assertSee('Save these recovery codes now');
        $this->assertNotSame($old, $admin->fresh()->two_factor_recovery_codes);
        $page->set('password', 'nope')->call('disable')->assertHasErrors('password');
        $this->assertTrue($admin->fresh()->hasTwoFactor());

        // A Super Admin can reset someone who lost their phone; staff with two-step sign in on the staff page.
        auth()->logout();
        $this->flushSession();
        $this->post('/staff', ['email' => $staff->email, 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        $this->post('/two-step', ['code' => $this->code($staff)])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($staff, 'web');
        $this->post('/logout');
        $this->flushSession();
        Livewire::actingAs($other)->test(UsersIndex::class, ['scope' => 'staff'])->assertSee('2-step')->call('resetTwoFactor', $staff->id);
        $this->assertFalse($staff->fresh()->hasTwoFactor());
        Livewire::actingAs($staff)->test(UsersIndex::class)->assertForbidden();

        // Requiring it: only someone who already has it can switch the rule on.
        Livewire::actingAs($other)->test(UsersIndex::class)->call('setRequireTwoFactor', true);
        $this->assertNotSame('1', setting('require_two_factor'));
        Livewire::actingAs($admin)->test(UsersIndex::class)->call('setRequireTwoFactor', true)->assertSee('Required');
        Setting::flush();
        $this->assertSame('1', setting('require_two_factor'));

        // A Super Admin without it is taken to setup and nowhere else; staff and those who have it carry on.
        $this->flushSession();
        $this->actingAs($other)->get('/')->assertRedirect(route('two-factor.setup'));
        $this->actingAs($other)->get(route('users.index'))->assertRedirect(route('two-factor.setup'));
        $this->actingAs($other)->get(route('two-factor.setup'))->assertOk()->assertSee('required for Super Admins');
        $this->actingAs($staff->fresh())->get('/')->assertOk();
        $this->flushSession();
        $this->actingAs($admin->fresh())->get('/')->assertOk();

        // While required, a Super Admin cannot switch their own off; once optional again they can.
        Livewire::actingAs($admin)->test(TwoFactor::class)->assertDontSee('Switch off two-step login')->set('password', 'correct-horse-1')->call('disable')->assertHasErrors('password');
        $this->assertTrue($admin->fresh()->hasTwoFactor());
        Livewire::actingAs($admin)->test(UsersIndex::class)->call('setRequireTwoFactor', false);
        Setting::flush();
        Livewire::actingAs($admin)->test(TwoFactor::class)->set('password', 'correct-horse-1')->call('disable')->assertHasNoErrors();
        $this->assertFalse($admin->fresh()->hasTwoFactor());
        $this->flushSession();
        $this->actingAs($other)->get('/')->assertOk();
    }
}
