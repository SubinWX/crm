<?php

namespace Tests\Feature;

use App\Livewire\Calendar\Index as Calendar;
use App\Livewire\Users\Index as UsersIndex;
use App\Models\Client;
use App\Models\HostingRecord;
use App\Models\Invoice;
use App\Models\Lookup;
use App\Models\Renewal;
use App\Models\Task;
use App\Models\User;
use App\Support\CalendarEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_calendar_lists_dated_items_by_day_and_respects_roles(): void
    {
        $this->travelTo('2026-11-11 06:00:00');
        $admin = User::factory()->superAdmin()->create(['name' => 'Asha Admin']);
        $staff = User::factory()->staff(['clients.view'])->create(['name' => 'Ravi Staff']);
        $sub = User::factory()->create(['role' => User::ROLE_SUB_ADMIN, 'permissions' => null, 'name' => 'Sam Sub']);

        // Date of birth is set on the Users screen for any role.
        Livewire::actingAs($admin)->test(UsersIndex::class, ['scope' => 'staff'])->call('edit', $staff->id)->set('form.date_of_birth', '1994-11-20')->call('save')->assertHasNoErrors();
        Livewire::actingAs($admin)->test(UsersIndex::class)->call('edit', $sub->id)->set('form.date_of_birth', '2000-02-29')->call('save')->assertHasNoErrors();
        Livewire::actingAs($admin)->test(UsersIndex::class)->call('edit', $admin->id)->set('form.date_of_birth', '2030-01-01')->call('save')->assertHasErrors('form.date_of_birth');
        $this->assertSame('1994-11-20', $staff->fresh()->date_of_birth->toDateString());

        $paid = Lookup::where('type', 'server_type')->where('name', 'Paid')->value('id');
        $client = Client::create(['name' => 'Saffron Kitchen', 'created_by' => $admin->id]);
        $own = Client::create(['name' => 'Sub Own Client', 'created_by' => $sub->id]);
        HostingRecord::create(['client_id' => $client->id, 'domain' => 'saffron.in', 'server_type_id' => $paid, 'next_payment_date' => '2026-11-05', 'renewal_amount' => 6500]);
        HostingRecord::create(['client_id' => $own->id, 'domain' => 'subown.in', 'server_type_id' => $paid, 'next_payment_date' => '2026-11-25']);
        Renewal::create(['client_id' => $client->id, 'type' => 'domain', 'name' => 'saffron-domain.in', 'renewal_date' => '2026-11-18']);
        Invoice::create(['number' => 'INV-C-1', 'client_id' => $client->id, 'invoice_date' => '2026-11-01', 'due_date' => '2026-11-22', 'total' => 900, 'status' => 'sent']);
        Task::create(['client_id' => $client->id, 'title' => 'Update menu prices', 'due_date' => '2026-11-14', 'assignee_id' => $staff->id]);

        // Super Admin: everything, on the right days.
        $calendar = Livewire::actingAs($admin)->test(Calendar::class)->assertSet('month', '2026-11')->assertSet('day', '2026-11-11');
        $byDay = $calendar->viewData('byDay');
        $titles = fn (string $date) => $byDay->get($date, collect())->pluck('title')->all();
        $this->assertContains('Saffron Kitchen', $titles('2026-11-11'));          // client added today
        $this->assertSame(['saffron.in'], $titles('2026-11-05'));
        $this->assertTrue($byDay['2026-11-05'][0]['alert']);                      // overdue hosting payment is flagged
        $this->assertSame(['Ravi Staff’s birthday'], $titles('2026-11-20'));
        $this->assertSame(['saffron-domain.in'], $titles('2026-11-18'));
        $this->assertSame(['INV-C-1 due'], $titles('2026-11-22'));
        $this->assertSame(['Update menu prices'], $titles('2026-11-14'));
        $calendar->assertSee('Birthdays')->assertSee('Invoices due')->assertSee('Hosting payments');

        // Picking a day lists that day; hiding a kind removes it; moving month works.
        $calendar->call('selectDay', '2026-11-22')->assertSee('Sunday, 22 November 2026')->assertSee('balance');
        $calendar->call('toggleType', 'invoice');
        $this->assertSame([], $calendar->viewData('byDay')->get('2026-11-22', collect())->all());
        $calendar->call('move', 1)->assertSet('month', '2026-12')->call('goToday')->assertSet('month', '2026-11');

        // Birthdays repeat every year; 29 February shows on the 28th outside leap years.
        $this->assertSame('2027-02-28', CalendarEvents::birthdayIn($sub->fresh()->date_of_birth, 2027));
        $this->assertSame('2028-02-29', CalendarEvents::birthdayIn($sub->fresh()->date_of_birth, 2028));
        $february = Livewire::actingAs($admin)->withQueryParams(['month' => '2027-02'])->test(Calendar::class)->viewData('byDay');
        $this->assertSame(['Sam Sub’s birthday'], $february->get('2027-02-28')->pluck('title')->all());

        // Staff without finance see no invoices; the year of birth is never shown.
        $staffView = Livewire::withQueryParams([])->actingAs($staff)->test(Calendar::class);
        $this->assertNotContains('invoice', $staffView->viewData('allowed'));
        $staffView->assertDontSee('INV-C-1')->assertDontSee('1994')->assertSee('Ravi Staff’s birthday');

        // Sub Admins see birthdays and only the clients (and hosting) they added.
        $subDays = Livewire::withQueryParams([])->actingAs($sub)->test(Calendar::class)->viewData('byDay');
        $all = $subDays->flatten(1)->pluck('title')->all();
        $this->assertContains('Sub Own Client', $all);
        $this->assertContains('subown.in', $all);
        $this->assertContains('Ravi Staff’s birthday', $all);
        foreach (['Saffron Kitchen', 'saffron.in', 'saffron-domain.in', 'INV-C-1 due', 'Update menu prices'] as $hidden) {
            $this->assertNotContains($hidden, $all);
        }

        // The page is in the menu for every team role and closed to guests.
        foreach ([$admin, $staff, $sub] as $user) {
            $this->actingAs($user)->get(route('calendar'))->assertOk()->assertSee('Calendar');
        }
        auth()->logout();
        $this->get(route('calendar'))->assertRedirect(route('login'));
    }
}
