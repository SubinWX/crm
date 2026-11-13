<?php

namespace Tests\Feature;

use App\Livewire\Account\Profile;
use App\Livewire\Tasks\Index;
use App\Livewire\Team\Attendance;
use App\Livewire\Team\Leave;
use App\Livewire\Team\Timesheet;
use App\Livewire\Work\Projects;
use App\Livewire\Work\ProjectShow;
use App\Models\AttendanceRecord;
use App\Models\Client;
use App\Models\Equipment;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Notifications\LeaveDecided;
use App\Notifications\LeaveRequested;
use App\Support\AttendanceSheet;
use App\Support\CalendarEvents;
use App\Support\LeaveBalance;
use App\Support\WorkCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class TeamWorkTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected User $ravi;

    protected User $meera;

    protected User $hr;

    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-12 06:00:00'); // Thursday 12 Nov 2026, 11:30 IST
        $this->admin = User::factory()->superAdmin()->create(['name' => 'Asha Admin']);
        $this->ravi = User::factory()->create(['name' => 'Ravi Staff', 'role' => User::ROLE_STAFF, 'permissions' => ['clients.view', 'projects.view', 'hosting.view']]);
        $this->meera = User::factory()->create(['name' => 'Meera Staff', 'role' => User::ROLE_STAFF, 'permissions' => ['clients.view', 'projects.view', 'hosting.view']]);
        $this->hr = User::factory()->create(['name' => 'Hari HR', 'role' => User::ROLE_STAFF, 'permissions' => ['team.manage', 'clients.view', 'projects.view', 'hosting.view']]);
        $this->client = Client::create(['name' => 'Kerala Traders']);
    }

    public function test_projects_have_a_description_team_and_estimate_and_show_hours_against_it(): void
    {
        // An admin adds a project with a description, dates, an estimate and a team. The description is cleaned like other editor text.
        $form = Livewire::actingAs($this->admin)->test(Projects::class)->call('create')->assertSee('Estimated hours')->assertSee('Team')
            ->set('form.client_id', (string) $this->client->id)->set('form.name', 'Website redesign')->set('form.status', 'active')
            ->set('form.description', '<div>Redesign the <strong>home page</strong>.</div><script>alert(1)</script>')
            ->set('form.start_date', '2026-11-01')->set('form.deadline', '2026-12-15')->set('form.estimated_hours', '-5')->call('save')->assertHasErrors('form.estimated_hours');
        $outsider = User::factory()->create(['role' => User::ROLE_SUB_ADMIN]);
        $form->set('form.estimated_hours', '10')->set('members', [(string) $this->ravi->id, (string) $outsider->id])->call('save')->assertHasErrors('members.1');
        $form->set('members', [(string) $this->ravi->id])->call('save')->assertHasNoErrors();

        $project = Project::firstOrFail();
        $this->assertSame(['<div>Redesign the <strong>home page</strong>.</div>', '10.00', '2026-11-01', [$this->ravi->id]],
            [$project->description, $project->estimated_hours, $project->start_date->toDateString(), $project->members->pluck('id')->all()]);
        Livewire::actingAs($this->admin)->test(Projects::class)->call('edit', $project->id)->assertSet('members', [(string) $this->ravi->id])->assertSet('form.estimated_hours', '10')
            ->set('members', [(string) $this->ravi->id, (string) $this->meera->id])->call('save')->assertHasNoErrors();
        $this->assertCount(2, $project->fresh()->members);

        // Staff without "manage clients" can read projects but not add or change them.
        Livewire::actingAs($this->ravi)->test(Projects::class)->assertSee('Website redesign')->call('create')->assertForbidden();

        // Hours logged are compared with the estimate, on the list and on the project's own page.
        TimeEntry::create(['user_id' => $this->ravi->id, 'project_id' => $project->id, 'work_date' => '2026-11-10', 'minutes' => 300, 'description' => 'Home page layout']);
        TimeEntry::create(['user_id' => $this->meera->id, 'project_id' => $project->id, 'work_date' => '2026-11-11', 'minutes' => 90, 'is_billable' => false]);
        $this->assertSame([65, null], [$project->progress(390), (new Project)->progress(390)]);
        Livewire::actingAs($this->admin)->test(Projects::class)->assertSee('6h 30m')->assertSee('of 10h');

        $page = Livewire::actingAs($this->admin)->test(ProjectShow::class, ['project' => $project]);
        $page->assertSee('Website redesign')->assertSee('home page')->assertSee('6h 30m')->assertSee('65%')->assertSee('3h 30m left')->assertSee('5h billable')
            ->assertSee('Ravi Staff')->assertSee('Meera Staff')->assertSee('Home page layout')->assertSee('Not billable')
            ->assertViewHas('byPerson', fn ($rows) => $rows->pluck('minutes', 'name')->map(fn ($m) => (int) $m)->all() === ['Ravi Staff' => 300, 'Meera Staff' => 90]);
        // A team member sees the totals but only their own line-by-line entries.
        Livewire::actingAs($this->ravi)->test(ProjectShow::class, ['project' => $project])->assertSee('6h 30m')->assertSee('Home page layout')
            ->assertViewHas('recent', fn ($rows) => $rows->count() === 1 && $rows->first()->user_id === $this->ravi->id);

        // Going over the estimate is flagged.
        TimeEntry::create(['user_id' => $this->ravi->id, 'project_id' => $project->id, 'work_date' => '2026-11-12', 'minutes' => 300]);
        Livewire::actingAs($this->admin)->test(ProjectShow::class, ['project' => $project])->assertSee('115%')->assertSee('1h 30m over the estimate');
        $this->actingAs($this->admin)->get(route('projects.show', $project))->assertOk()->assertSee('Hours spent against the estimate');
        $this->actingAs($outsider)->get(route('projects.show', $project))->assertForbidden();
    }

    public function test_staff_log_time_and_managers_see_the_whole_team(): void
    {
        $open = Project::create(['client_id' => $this->client->id, 'name' => 'Open project', 'status' => 'active']);
        $ravis = Project::create(['client_id' => $this->client->id, 'name' => 'Ravi only', 'status' => 'active']);
        $ravis->members()->sync([$this->ravi->id]);
        $closed = Project::create(['client_id' => $this->client->id, 'name' => 'Finished', 'status' => 'completed']);

        // Projects on offer: open ones you are on, or with no fixed team.
        $this->assertSame(['Open project', 'Ravi only'], Project::loggableBy($this->ravi)->orderBy('name')->pluck('name')->all());
        $this->assertSame(['Open project'], Project::loggableBy($this->meera)->pluck('name')->all());

        $sheet = Livewire::actingAs($this->ravi)->test(Timesheet::class)->assertSee('09 Nov')->assertSee('15 Nov 2026')->call('create');
        // Hours, date and project are checked.
        $sheet->set('form.project_id', (string) $ravis->id)->set('form.hours', '0.1')->call('save')->assertHasErrors('form.hours');
        $sheet->set('form.hours', '2.5')->set('form.work_date', '2026-11-13')->call('save')->assertHasErrors('form.work_date');   // tomorrow
        $sheet->set('form.work_date', '2026-10-20')->call('save')->assertHasErrors('form.work_date');                           // older than 14 days
        $sheet->set('form.work_date', '2026-11-12')->set('form.project_id', (string) $closed->id)->call('save')->assertHasErrors('form.project_id');
        $sheet->set('form.project_id', (string) $ravis->id)->set('form.description', 'Wireframes')->call('save')->assertHasNoErrors();
        $entry = TimeEntry::firstOrFail();
        $this->assertSame([$this->ravi->id, $ravis->id, 150, '2026-11-12', true], [$entry->user_id, $entry->project_id, $entry->minutes, $entry->work_date->toDateString(), $entry->is_billable]);

        // No more than 24 hours in a day.
        $sheet->call('create')->set('form.project_id', (string) $open->id)->set('form.hours', '22')->set('form.work_date', '2026-11-12')->call('save')->assertHasErrors('form.hours');
        $sheet->set('form.hours', '4')->set('form.is_billable', false)->call('save')->assertHasNoErrors();
        $sheet->assertSee('6h 30m')->assertSee('Wireframes')->assertViewHas('billable', 150)->assertViewHas('days', fn ($days) => $days->firstWhere('date', '2026-11-12')['minutes'] === 390 && $days->count() === 7);

        // Meera cannot log to Ravi's project, nor touch his entry; Ravi can edit and delete his own.
        Livewire::actingAs($this->meera)->test(Timesheet::class)->call('create')->set('form.project_id', (string) $ravis->id)->set('form.hours', '1')->call('save')->assertHasErrors('form.project_id');
        Livewire::actingAs($this->meera)->test(Timesheet::class)->call('edit', $entry->id)->assertForbidden();
        Livewire::actingAs($this->meera)->test(Timesheet::class)->call('delete', $entry->id)->assertForbidden();
        Livewire::actingAs($this->ravi)->test(Timesheet::class)->call('edit', $entry->id)->assertSet('form.hours', '2.5')->set('form.hours', '3')->call('save')->assertHasNoErrors();
        $this->assertSame(180, $entry->fresh()->minutes);

        // Staff only ever see their own time, whatever the address says.
        TimeEntry::create(['user_id' => $this->meera->id, 'project_id' => $open->id, 'work_date' => '2026-11-11', 'minutes' => 60, 'description' => 'Meera research']);
        Livewire::actingAs($this->ravi)->withQueryParams(['person' => 'all'])->test(Timesheet::class)->assertDontSee('Meera research')->assertViewHas('total', 420);

        // A manager sees one person or everyone, can correct any entry, and exports the week.
        $all = Livewire::actingAs($this->hr)->withQueryParams(['person' => 'all'])->test(Timesheet::class);
        $all->assertSee('Meera research')->assertSee('Wireframes')->assertViewHas('total', 480)->assertSee('Everyone')
            ->set('person', (string) $this->meera->id)->assertViewHas('total', 60)->assertDontSee('Wireframes');
        Livewire::actingAs($this->hr)->test(Timesheet::class)->call('edit', $entry->id)->set('form.hours', '2')->call('save')->assertHasNoErrors();
        $this->assertSame([120, $this->ravi->id], [$entry->fresh()->minutes, $entry->fresh()->user_id]);
        $csv = $this->actingAs($this->hr)->get(route('timesheet.export', ['week' => '2026-11-12', 'person' => 'all']))->assertOk()->streamedContent();
        $this->assertStringContainsString('2026-11-12,"Ravi Staff","Kerala Traders","Ravi only",Wireframes,,,02:00:00,2.00,yes', $csv);
        $this->assertStringContainsString('"Meera research"', $csv);

        // Week navigation, and an entry older than the window is locked for staff.
        Livewire::actingAs($this->ravi)->test(Timesheet::class)->call('shift', -1)->assertSee('02 Nov')->assertViewHas('total', 0)->call('thisWeek')->assertViewHas('total', 360);
        $old = TimeEntry::create(['user_id' => $this->ravi->id, 'project_id' => $open->id, 'work_date' => '2026-10-01', 'minutes' => 60]);
        Livewire::actingAs($this->ravi)->test(Timesheet::class)->call('delete', $old->id)->assertForbidden();
        Livewire::actingAs($this->ravi)->test(Timesheet::class)->call('delete', $entry->id);
        $this->assertNull($entry->fresh());

        $sub = User::factory()->create(['role' => User::ROLE_SUB_ADMIN]);
        foreach (['timesheet', 'leave', 'attendance'] as $route) {
            $this->actingAs($sub)->get(route($route))->assertForbidden();
            $this->actingAs($this->ravi)->get(route($route))->assertOk();
        }
    }

    public function test_leave_is_applied_for_by_staff_and_approved_or_rejected_by_managers(): void
    {
        Notification::fake();
        Holiday::create(['date' => '2026-11-18', 'name' => 'Local festival']);
        $casual = Lookup::where('type', 'leave_type')->where('name', 'Casual Leave')->value('id');
        $unpaid = Lookup::where('type', 'leave_type')->where('name', 'Loss of Pay')->value('id');

        // Working days skip Sundays (the default weekly off) and holidays; a half day counts as 0.5.
        $this->assertSame([5.0, 0.5, 0.0, 0.0], [
            WorkCalendar::workingDays('2026-11-16', '2026-11-22'), WorkCalendar::workingDays('2026-11-16', '2026-11-16', 'first_half'),
            WorkCalendar::workingDays('2026-11-15', '2026-11-15'), WorkCalendar::workingDays('2026-11-18', '2026-11-18'),
        ]);

        $page = Livewire::actingAs($this->ravi)->test(Leave::class)->assertSee('Casual Leave')->assertSee('left of 12')->assertDontSee('To approve')->call('apply');
        $page->call('submit')->assertHasErrors(['form.leave_type_id', 'form.reason']);
        $page->set('form.leave_type_id', (string) $casual)->set('form.reason', 'Family function')->set('form.from_date', '2026-11-20')->set('form.to_date', '2026-11-16')->call('submit')->assertHasErrors('form.to_date');
        $page->set('form.from_date', '2026-09-01')->set('form.to_date', '2026-09-02')->call('submit')->assertHasErrors('form.from_date');   // too far back
        $page->set('form.from_date', '2026-11-15')->set('form.to_date', '2026-11-15')->call('submit')->assertHasErrors('form.from_date');   // a Sunday
        $page->set('form.from_date', '2026-11-16')->set('form.to_date', '2026-11-22')->assertSee('5 working days')->call('submit')->assertHasNoErrors();

        $leave = LeaveRequest::firstOrFail();
        $this->assertSame([$this->ravi->id, 'pending', '5.00', 'full'], [$leave->user_id, $leave->status, $leave->days, $leave->day_part]);
        Notification::assertSentTo([$this->admin, $this->hr], LeaveRequested::class);
        Notification::assertNotSentTo([$this->ravi, $this->meera], LeaveRequested::class);
        $this->assertStringContainsString('Ravi Staff asked for 5 days of casual leave: 16 Nov 2026 to 22 Nov 2026.', (new LeaveRequested($leave))->message());

        // Overlapping dates are refused while that request is pending or approved; a half day on another date is fine.
        $page->call('apply')->set('form.leave_type_id', (string) $casual)->set('form.reason', 'Again')->set('form.from_date', '2026-11-21')->set('form.to_date', '2026-11-24')->call('submit')->assertHasErrors('form.from_date');
        $page->set('form.from_date', '2026-11-25')->set('form.to_date', '2026-11-25')->set('form.day_part', 'second_half')->set('form.leave_type_id', (string) $unpaid)->call('submit')->assertHasNoErrors();
        $half = LeaveRequest::latest('id')->first();
        $this->assertSame(['0.50', 'second_half', '25 Nov 2026 (second half)'], [$half->days, $half->day_part, $half->period()]);

        // Staff cannot approve, and see only their own requests.
        Livewire::actingAs($this->meera)->test(Leave::class)->assertDontSee('Family function')->call('decide', $leave->id, 'approved')->assertForbidden();
        Livewire::actingAs($this->meera)->withQueryParams(['tab' => 'all'])->test(Leave::class)->assertSet('tab', 'mine')->assertDontSee('Family function');
        Livewire::actingAs($this->meera)->test(Leave::class)->call('cancel', $leave->id)->assertForbidden();

        // A manager approves one and rejects the other; a rejection needs a reason, and the person is told.
        $review = Livewire::actingAs($this->hr)->withQueryParams(['tab' => 'review'])->test(Leave::class)->assertSee('Ravi Staff')->assertSee('Family function')->assertViewHas('pendingCount', 2);
        $review->call('decide', $leave->id, 'approved')->assertSee('Approve leave for Ravi Staff')->call('confirmDecision')->assertHasNoErrors();
        $review->call('decide', $half->id, 'rejected')->call('confirmDecision')->assertHasErrors('note');
        $review->set('note', 'Launch week, please pick another day.')->call('confirmDecision')->assertHasNoErrors()->assertViewHas('pendingCount', 0);
        $this->assertSame(['approved', $this->hr->id, null], [$leave->fresh()->status, $leave->fresh()->reviewed_by, $leave->fresh()->review_note]);
        $this->assertSame(['rejected', 'Launch week, please pick another day.'], [$half->fresh()->status, $half->fresh()->review_note]);
        Notification::assertSentToTimes($this->ravi, LeaveDecided::class, 2);
        $this->assertSame('Your leave was approved', (new LeaveDecided($leave->fresh()))->title());
        $this->assertSame('Your leave was not approved', (new LeaveDecided($half->fresh()))->title());

        // The balance counts approved paid days; the rejected and unpaid ones are not counted.
        Livewire::actingAs($this->ravi)->test(Leave::class)->assertViewHas('balances', fn ($b) => ($cl = $b->firstWhere('code', 'CL')) && $cl['used'] === 5.0 && $cl['left'] === 7.0 && $cl['pending'] === 0.0 && $b->firstWhere('code', 'LOP')['used'] === 0.0)
            ->assertSee('Approved')->assertSee('Rejected')->assertSee('Launch week, please pick another day.');
        // A date that was rejected can be applied for again.
        Livewire::actingAs($this->ravi)->test(Leave::class)->call('apply')->set('form.leave_type_id', (string) $unpaid)->set('form.reason', 'Second try')
            ->set('form.from_date', '2026-11-25')->set('form.to_date', '2026-11-25')->call('submit')->assertHasNoErrors();
        $again = LeaveRequest::latest('id')->first();

        // Cancelling: a pending request, or approved leave that has not started. Leave already under way cannot be cancelled.
        Livewire::actingAs($this->ravi)->test(Leave::class)->call('cancel', $again->id);
        $this->assertSame('cancelled', $again->fresh()->status);
        Livewire::actingAs($this->ravi)->test(Leave::class)->call('cancel', $leave->id);
        $this->assertSame('cancelled', $leave->fresh()->status);
        $leave->update(['status' => 'approved']);
        $this->travelTo('2026-11-17 06:00:00');
        Livewire::actingAs($this->ravi)->test(Leave::class)->call('cancel', $leave->id)->assertStatus(422);

        // A manager cannot decide their own request (a second person must); a Super Admin can decide theirs.
        $own = LeaveRequest::create(['user_id' => $this->hr->id, 'leave_type_id' => $casual, 'from_date' => '2026-12-01', 'to_date' => '2026-12-01', 'days' => 1, 'reason' => 'Personal']);
        Livewire::actingAs($this->hr)->test(Leave::class)->call('decide', $own->id, 'approved')->call('confirmDecision')->assertHasErrors('note');
        $this->assertSame('pending', $own->fresh()->status);
        Livewire::actingAs($this->admin)->test(Leave::class)->call('decide', $own->id, 'approved')->call('confirmDecision')->assertHasNoErrors();
        $this->assertSame('approved', $own->fresh()->status);

        // Managers see who is away today and everyone's leave; approved leave and holidays show on the Calendar.
        Livewire::actingAs($this->hr)->withQueryParams(['tab' => 'all'])->test(Leave::class)->assertSee('On leave today:')->assertSee('Ravi Staff')->assertSee('Hari HR')
            ->set('person', (string) $this->hr->id)->assertViewHas('list', fn ($rows) => $rows->pluck('user_id')->unique()->all() === [$this->hr->id]);
        $events = CalendarEvents::between($this->hr, today_local()->startOfMonth(), today_local()->endOfMonth(), ['team']);
        $this->assertSame(['2026-11-16', '2026-11-17', '2026-11-19', '2026-11-20', '2026-11-21'], $events->where('title', 'Ravi Staff on leave · CL')->pluck('date')->all());
        // Other staff see holidays but not a colleague's leave; the person sees it as "My leave".
        $this->assertSame(0, CalendarEvents::between($this->meera, today_local()->startOfMonth(), today_local()->endOfMonth(), ['team'])->filter(fn ($e) => str_contains($e['title'], 'on leave'))->count());
        $this->assertSame(5, CalendarEvents::between($this->ravi, today_local()->startOfMonth(), today_local()->endOfMonth(), ['team'])->where('title', 'My leave · CL')->count());
        $this->assertSame(['2026-11-18'], $events->where('title', 'Local festival')->pluck('date')->all());
    }

    public function test_attendance_is_checked_in_marked_by_managers_and_summarised_by_month(): void
    {
        $casual = Lookup::where('type', 'leave_type')->where('name', 'Casual Leave')->value('id');
        Holiday::create(['date' => '2026-11-04', 'name' => 'Founders day']);
        LeaveRequest::create(['user_id' => $this->ravi->id, 'leave_type_id' => $casual, 'from_date' => '2026-11-05', 'to_date' => '2026-11-06', 'days' => 2, 'status' => 'approved']);
        LeaveRequest::create(['user_id' => $this->ravi->id, 'leave_type_id' => $casual, 'from_date' => '2026-11-07', 'to_date' => '2026-11-07', 'day_part' => 'first_half', 'days' => 0.5, 'status' => 'approved']);
        LeaveRequest::create(['user_id' => $this->ravi->id, 'leave_type_id' => $casual, 'from_date' => '2026-11-09', 'to_date' => '2026-11-09', 'days' => 1, 'status' => 'pending']);

        // Checking in records the time in the business time zone; checking out completes the day.
        $mine = Livewire::actingAs($this->ravi)->test(Attendance::class)->assertSee('You have not checked in yet')->assertDontSee('Team sheet');
        $mine->call('checkOut');
        $this->assertSame(0, AttendanceRecord::count());
        $mine->call('checkIn')->assertSee('Checked in at')->assertSee('11:30 AM');
        $record = AttendanceRecord::firstOrFail();
        $this->assertSame([$this->ravi->id, '2026-11-12', 'present', '11:30:00', null], [$record->user_id, $record->date->toDateString(), $record->status, $record->check_in, $record->check_out]);
        $mine->call('checkIn');
        $this->assertSame(1, AttendanceRecord::count()); // a second press does nothing
        $this->travelTo('2026-11-12 12:30:00');
        $mine->call('checkOut')->assertSee('6:00 PM')->assertSee('Done for today');
        $this->assertSame('18:00:00', $record->fresh()->check_out);

        // The month grid: marks, approved leave, holidays and weekly offs; past working days with nothing are "not marked".
        $sheet = new AttendanceSheet('2026-11', collect([$this->ravi]));
        $codes = fn (array $dates) => array_map(fn ($d) => $sheet->cells[$this->ravi->id][$d]['code'], $dates);
        $this->assertSame(['W', '?', '?', 'H', 'L', 'L', 'HL', 'W', '?', '?', '?', 'P', '', ''], $codes(array_map(fn ($d) => sprintf('2026-11-%02d', $d), range(1, 14))));
        $this->assertSame('Founders day', $sheet->cells[$this->ravi->id]['2026-11-04']['note']);
        $this->assertSame(['present' => 1.5, 'absent' => 0.0, 'leave' => 2.5, 'unmarked' => 5, 'working' => 24], $sheet->totals[$this->ravi->id]);
        $mine->assertSee('Not marked')->assertSee('Founders day')->assertSee('Casual Leave');

        // Staff cannot open the team sheet or change anyone's day.
        Livewire::actingAs($this->ravi)->withQueryParams(['tab' => 'sheet'])->test(Attendance::class)->assertSet('tab', 'mine')
            ->call('editCell', $this->meera->id, '2026-11-10')->assertForbidden();
        Livewire::actingAs($this->ravi)->test(Attendance::class)->call('markDayPresent', '2026-11-10')->assertForbidden();
        Livewire::actingAs($this->ravi)->test(Attendance::class)->call('addHoliday')->assertForbidden();
        $this->actingAs($this->ravi)->get(route('attendance.export', ['month' => '2026-11']))->assertForbidden();

        // A manager corrects single days, fills a whole day at once, and clears a mark.
        $team = Livewire::actingAs($this->hr)->withQueryParams(['tab' => 'sheet'])->test(Attendance::class)->assertSee('Ravi Staff')->assertSee('Meera Staff')->assertSee('Asha Admin');
        $team->call('editCell', $this->ravi->id, '2026-11-13')->assertForbidden(); // tomorrow
        $team = Livewire::actingAs($this->hr)->withQueryParams(['tab' => 'sheet'])->test(Attendance::class);
        $team->call('editCell', $this->ravi->id, '2026-11-10')->assertSee('Ravi Staff · Tue, 10 Nov 2026')->set('cell.status', 'absent')->set('cell.note', 'No call')->call('saveCell')->assertHasNoErrors();
        $team->call('editCell', $this->meera->id, '2026-11-10')->set('cell.status', 'wfh')->call('saveCell');
        $this->assertSame(['absent', 'No call', $this->hr->id], array_values(AttendanceRecord::where('user_id', $this->ravi->id)->whereDate('date', '2026-11-10')->firstOrFail()->only(['status', 'note', 'marked_by'])));
        $team->call('markDayPresent', '2026-11-11');
        $this->assertSame(4, AttendanceRecord::whereDate('date', '2026-11-11')->where('status', 'present')->count()); // all four team members
        $team->call('markDayPresent', '2026-11-05'); // Ravi is on leave that day and is left alone
        $this->assertNull(AttendanceRecord::where('user_id', $this->ravi->id)->whereDate('date', '2026-11-05')->first());
        $this->assertSame(3, AttendanceRecord::whereDate('date', '2026-11-05')->count());
        // A manual mark wins over leave; clearing it brings the leave back.
        $team->call('editCell', $this->ravi->id, '2026-11-06')->set('cell.status', 'present')->call('saveCell');
        $this->assertSame('P', (new AttendanceSheet('2026-11', collect([$this->ravi])))->cells[$this->ravi->id]['2026-11-06']['code']);
        $team->call('editCell', $this->ravi->id, '2026-11-06')->assertSet('cell.status', 'present')->set('cell.status', '')->call('saveCell');
        $this->assertSame('L', (new AttendanceSheet('2026-11', collect([$this->ravi])))->cells[$this->ravi->id]['2026-11-06']['code']);

        // Holidays and the working week are set by managers and change the grid and the leave count.
        $setup = Livewire::actingAs($this->hr)->withQueryParams(['tab' => 'setup'])->test(Attendance::class)->assertSee('Company holidays')->assertSee('Founders day');
        $setup->set('holiday.date', '2026-11-04')->set('holiday.name', 'Duplicate')->call('addHoliday')->assertHasErrors('holiday.date');
        $setup->set('holiday.date', '2026-11-27')->set('holiday.name', 'Annual day')->call('addHoliday')->assertHasNoErrors()->assertSee('Annual day');
        $setup->set('setup.weekly_off', ['0', '1', '2', '3', '4', '5', '6'])->call('saveSetup')->assertHasErrors('setup.weekly_off');
        $setup->set('setup.weekly_off', ['0', '6'])->call('saveSetup')->assertHasNoErrors();
        Setting::flush();
        $this->assertSame([0, 6], WorkCalendar::weeklyOff());
        $this->assertSame(4.0, WorkCalendar::workingDays('2026-11-23', '2026-11-29')); // Mon–Fri minus the new holiday
        $this->assertSame('W', (new AttendanceSheet('2026-11', collect([$this->meera])))->cells[$this->meera->id]['2026-11-07']['code']);
        $this->actingAs($this->hr);
        $setup->call('deleteHoliday', Holiday::where('name', 'Annual day')->value('id'));
        $this->assertSame(1, Holiday::count());

        // The sheet downloads as a spreadsheet with one row per person and the totals.
        $csv = $this->actingAs($this->hr)->get(route('attendance.export', ['month' => '2026-11']))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Ravi Staff",W,?,?,H,L,L,W,W,?,A,P,P,', $csv);
        $this->assertStringContainsString('P = present', $csv);
        $this->actingAs($this->hr)->get(route('attendance.export', ['month' => 'nope']))->assertNotFound();
        $this->actingAs($this->hr)->get(route('dashboard'))->assertOk()->assertSee('Timesheet')->assertSee('Attendance');
    }

    public function test_the_timer_tracks_time_per_project_and_projects_show_time_by_person(): void
    {
        $site = Project::create(['client_id' => $this->client->id, 'name' => 'Partners page', 'status' => 'active']);
        $internal = Project::create(['client_id' => $this->client->id, 'name' => 'Internal comms', 'status' => 'active']);
        $closed = Project::create(['client_id' => $this->client->id, 'name' => 'Old site', 'status' => 'completed']);

        // Starting needs one of your open projects.
        $tracker = Livewire::actingAs($this->ravi)->test(Timesheet::class)->assertSee('Tracker')->assertSee('Timer')->assertSee('Manual')->assertSee('Start');
        $tracker->call('startTimer')->assertHasErrors('timer.project_id');
        $tracker->set('timer.project_id', (string) $closed->id)->call('startTimer')->assertHasErrors('timer.project_id');
        $tracker->set('timer.project_id', (string) $site->id)->set('timer.description', 'Partners nav update')->call('startTimer')->assertHasNoErrors();

        $entry = TimeEntry::firstOrFail();
        $this->assertSame([$this->ravi->id, $site->id, 'Partners nav update', 0, '2026-11-12', true, true], [$entry->user_id, $entry->project_id, $entry->description, $entry->minutes, $entry->work_date->toDateString(), $entry->isRunning(), $entry->is_billable]);
        $tracker->assertSee('Stop')->assertSee('Partners nav update')->assertSee('Partners page')->assertViewHas('total', 0); // a running timer is not in the totals yet
        // It shows in the top bar on every page while it runs, and cannot be edited until stopped.
        $this->actingAs($this->ravi)->get(route('dashboard'))->assertOk()->assertSee('Timer running on Partners page');
        Livewire::actingAs($this->ravi)->test(Timesheet::class)->call('edit', $entry->id)->assertStatus(422);

        // Stopping saves the exact start and end, and the time rounded to the minute.
        $this->travel(62)->minutes();
        $this->travel(20)->seconds();
        Livewire::actingAs($this->ravi)->test(Timesheet::class)->call('stopTimer')->assertSee('Start')->assertDontSee('Discard');
        $entry->refresh();
        $this->assertSame([false, 62, 3740, '01:02:20'], [$entry->isRunning(), $entry->minutes, $entry->seconds(), TimeEntry::clock($entry->seconds())]);
        $this->assertSame(['11:30 AM', '12:32 PM'], [$entry->started_at->timezone(crm_tz())->format('g:i A'), $entry->ended_at->timezone(crm_tz())->format('g:i A')]);

        // Starting a second timer stops the first; the play button restarts earlier work on the same project.
        $tracker = Livewire::actingAs($this->ravi)->test(Timesheet::class);
        $tracker->set('timer.project_id', (string) $internal->id)->set('timer.description', 'Team call')->call('startTimer');
        $this->travel(30)->minutes();
        $tracker->call('restart', $entry->id);
        $this->assertSame(1, TimeEntry::running()->count());
        $call = TimeEntry::where('description', 'Team call')->firstOrFail();
        $this->assertSame([30, false], [$call->minutes, $call->isRunning()]);
        $running = TimeEntry::running()->firstOrFail();
        $this->assertSame([$site->id, 'Partners nav update'], [$running->project_id, $running->description]);
        // Someone else cannot restart or stop your timer; discarding throws the running one away.
        Livewire::actingAs($this->meera)->test(Timesheet::class)->call('restart', $entry->id)->assertForbidden();
        Livewire::actingAs($this->meera)->test(Timesheet::class)->call('stopTimer');
        $this->assertTrue($running->fresh()->isRunning());
        Livewire::actingAs($this->ravi)->test(Timesheet::class)->call('discardTimer');
        $this->assertNull($running->fresh());

        // A timer left running is cut off at the cap, not left to run for days.
        $forgotten = TimeEntry::create(['user_id' => $this->meera->id, 'project_id' => $internal->id, 'work_date' => today_local()->toDateString(), 'minutes' => 0, 'started_at' => now()]);
        $this->travel(40)->hours();
        $forgotten->stop();
        $this->assertSame(TimeEntry::MAX_TIMER_HOURS * 60, $forgotten->fresh()->minutes);
        $this->travelBack();
        $this->travelTo('2026-11-12 08:02:20');

        // Manual mode adds finished work in one line; mistakes show under that line.
        $manual = Livewire::actingAs($this->ravi)->test(Timesheet::class)->set('mode', 'manual')->assertSee('Add');
        $manual->set('quick.hours', '2')->call('quickAdd')->assertHasErrors('quick.project_id');
        $manual->set('quick.project_id', (string) $internal->id)->set('quick.description', 'Case study template')->set('quick.hours', '30')->call('quickAdd')->assertHasErrors('quick.hours');
        $manual->set('quick.hours', '1.5')->call('quickAdd')->assertHasNoErrors()->assertSet('quick.hours', '')->assertSet('quick.description', '');
        $byHand = TimeEntry::where('description', 'Case study template')->firstOrFail();
        $this->assertSame([90, null, 5400], [$byHand->minutes, $byHand->started_at, $byHand->seconds()]);

        // The tracker lists the week by day with each day's total; typing over a timed entry's hours drops its start and end.
        $page = Livewire::actingAs($this->ravi)->test(Timesheet::class);
        $page->assertSee('Today')->assertSee('Week total')->assertSee('Partners nav update')->assertSee('Added by hand')->assertSee('11:30 AM')
            ->assertViewHas('weekSeconds', 3740 + 1800 + 5400)
            ->assertViewHas('groups', fn ($groups) => $groups->keys()->all() === ['2026-11-12'] && $groups['2026-11-12']['label'] === 'Today' && $groups['2026-11-12']['rows']->count() === 3);
        $page->call('edit', $entry->id)->set('form.hours', '2')->call('save')->assertHasNoErrors();
        $this->assertSame([120, null, null], [$entry->fresh()->minutes, $entry->fresh()->started_at, $entry->fresh()->ended_at]);
        // Overview: totals, hours per day and the split by project for a date range, with filters, and the matching export.
        $ov = Livewire::actingAs($this->ravi)->test(Timesheet::class)->set('tab', 'overview');
        $ov->assertSee('Total hours')->assertSee('Total billable')->assertSee('Total non-billable')->assertSee('Hours per day')->assertSee('Summary')->assertDontSee('Week total')
            ->assertSet('from', '2026-11-01')->assertSet('to', '2026-11-12')
            ->assertViewHas('overview', fn ($o) => $o['total'] === 7200 + 1800 + 5400 && $o['billable'] === $o['total'] && $o['nonBillable'] === 0 && $o['bars']->count() === 12
                && $o['bars']->last() === ['label' => 'Thu, Nov 12', 'seconds' => 14400] && $o['rows']->total() === 3
                && $o['slices']->pluck('seconds', 'name')->sortKeys()->all() === ['Internal comms' => 7200, 'Partners page' => 7200] && abs($o['slices']->sum('percent') - 100) < 0.1);
        $ov->set('task', 'case study')->assertViewHas('overview', fn ($o) => $o['total'] === 5400 && $o['rows']->total() === 1)->assertSee('Case study template')->assertDontSee('Team call')->set('task', '');
        $ov->set('project', (string) $site->id)->assertViewHas('overview', fn ($o) => $o['total'] === 7200)->set('project', '');
        $ov->set('from', '2026-10-01')->set('to', '2026-10-31')->assertViewHas('overview', fn ($o) => $o['total'] === 0 && $o['slices']->isEmpty())->assertSee('No time entries match')
            ->call('setRange', 'month')->assertSet('from', '2026-11-01')->set('perPage', 25)->assertSet('perPage', 25)->set('perPage', 7)->assertSet('perPage', 10);
        // A range typed the wrong way round still works.
        $ov->set('from', '2026-11-12')->set('to', '2026-11-01')->assertViewHas('overview', fn ($o) => $o['from'] === '2026-11-01' && $o['total'] === 14400);
        // Staff export their own time for the filters on screen, and never anyone else's.
        $own = $this->actingAs($this->ravi)->get(route('timesheet.export', ['from' => '2026-11-01', 'to' => '2026-11-12', 'person' => 'all']))->assertOk()->streamedContent();
        $this->assertStringContainsString('"Case study template",,,01:30:00,1.50,yes', $own);
        $this->assertStringNotContainsString('Meera Staff', $own);

        // The Projects page shows time tracked by each person, with where it went and who is working right now.
        TimeEntry::create(['user_id' => $this->meera->id, 'project_id' => $site->id, 'work_date' => '2026-10-20', 'minutes' => 240]);
        TimeEntry::create(['user_id' => $this->hr->id, 'project_id' => $site->id, 'work_date' => today_local()->toDateString(), 'minutes' => 0, 'started_at' => now()]);
        Livewire::actingAs($this->admin)->test(Projects::class)->assertSee('Time tracked by each person')->assertSee('Working now')
            ->assertViewHas('tracked', function ($tracked) {
                $rows = $tracked['people']->keyBy('name');

                return $rows['Ravi Staff']['today'] === 240 && $rows['Ravi Staff']['month'] === 240 && $rows['Ravi Staff']['total'] === 240
                    && $rows['Ravi Staff']['projects']->pluck('minutes', 'name')->map(fn ($m) => (int) $m)->sortKeys()->all() === ['Internal comms' => 120, 'Partners page' => 120]
                    // Meera: the capped forgotten timer today, plus four hours last month that count only in "all time".
                    && $rows['Meera Staff']['today'] === 720 && $rows['Meera Staff']['month'] === 720 && $rows['Meera Staff']['total'] === 960
                    && $rows['Hari HR']['running'] !== null && $rows['Hari HR']['total'] === 0
                    && $tracked['totals'] === ['today' => 960, 'week' => 960, 'month' => 960, 'total' => 1200];
            });
        // Inside a client's profile the projects tab stays a plain list.
        Livewire::actingAs($this->admin)->test(Projects::class, ['clientId' => $this->client->id])->assertDontSee('Time tracked by each person');
        // Running timers are left out of the export until they are stopped.
        $csv = $this->actingAs($this->hr)->get(route('timesheet.export', ['week' => today_local()->toDateString(), 'person' => 'all']))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Hari HR', $csv);
        $this->assertStringContainsString('"Case study template"', $csv);
    }

    public function test_tasks_sit_under_projects_with_several_people_working_on_them(): void
    {
        $project = Project::create(['client_id' => $this->client->id, 'name' => 'Banner creation', 'owner_id' => $this->admin->id, 'status' => 'active']);
        $project->members()->sync([$this->ravi->id, $this->meera->id]);
        $other = Project::create(['client_id' => Client::create(['name' => 'Another client'])->id, 'name' => 'Elsewhere', 'status' => 'active']);

        // Added from the project page: the client and project are fixed, and two people work on it.
        Livewire::actingAs($this->admin)->test(Index::class, ['projectId' => $project->id])
            ->call('create')->set('form.title', 'Design three banner sizes')->set('assignees', [(string) $this->ravi->id, (string) $this->meera->id])
            ->call('save')->assertHasNoErrors()->assertSee('Design three banner sizes');
        $task = Task::where('title', 'Design three banner sizes')->firstOrFail();
        $this->assertSame([$project->id, $this->client->id], [$task->project_id, $task->client_id]);
        $this->assertEqualsCanonicalizing([$this->ravi->id, $this->meera->id], $task->assignees()->pluck('users.id')->all());

        // On the Tasks page a project of another client is refused, and the project filter narrows the list.
        Livewire::actingAs($this->admin)->test(Index::class)
            ->call('create')->set('form.client_id', (string) $this->client->id)->set('form.title', 'Loose task')->set('form.project_id', (string) $other->id)
            ->call('save')->assertHasErrors('form.project_id')
            ->set('form.project_id', '')->call('save')->assertHasNoErrors()
            ->assertSee('Loose task')->assertSee('Banner creation')
            ->set('project', (string) $project->id)->assertSee('Design three banner sizes')->assertDontSee('Loose task')
            ->set('project', 'none')->assertSee('Loose task')->assertDontSee('Design three banner sizes');

        // Both people find it under "Assigned to me" and may move it along.
        foreach ([$this->ravi, $this->meera] as $person) {
            Livewire::actingAs($person)->test(Index::class)->set('assignee', 'me')->assertSee('Design three banner sizes')->assertDontSee('Loose task');
        }
        Livewire::actingAs($this->meera)->test(Index::class)->call('setStatus', $task->id, 'in_progress')->assertOk();
        $this->assertSame('in_progress', $task->fresh()->status);

        // The project page and the project list show the tasks.
        Livewire::actingAs($this->admin)->test(ProjectShow::class, ['project' => $project])->assertSee('Tasks in this project')->assertSee('1 open · 0 done');
        Livewire::actingAs($this->admin)->test(Projects::class)->assertSee('Working on it')->assertSee('open of 1');
    }

    public function test_leave_types_have_yearly_allowances_with_automatic_and_custom_balances(): void
    {
        Notification::fake();
        $type = fn (string $code) => Lookup::where('type', 'leave_type')->where('code', $code)->firstOrFail();
        $row = fn (User $u, string $code, int $year = 2026) => LeaveBalance::for($u->fresh(), $year)->firstWhere('code', $code);

        // Automatic: CL 12, RH 2, ML 2 a year given in halves (both halves are out by November), EL only what is added, LOP no limit.
        $this->assertSame([12.0, 2.0, 2.0, 0.0, null], [$row($this->ravi, 'CL')['left'], $row($this->ravi, 'RH')['left'], $row($this->ravi, 'ML')['left'], $row($this->ravi, 'EL')['left'], $row($this->ravi, 'LOP')['left']]);
        $this->assertSame(1.0, LeaveBalance::allowance($this->ravi, $type('ML'), 2026, null, Carbon::parse('2026-03-10')));
        $this->assertSame(1.0, $row($this->ravi, 'ML', 2027)['allowance']);   // next year: only the first half so far

        // CL becomes 18 from the year in which someone completes two years.
        $this->meera->update(['joined_on' => '2024-06-01']);
        $this->ravi->update(['joined_on' => '2025-06-01']);
        $this->assertSame([18.0, 12.0, 18.0], [$row($this->meera, 'CL')['allowance'], $row($this->ravi, 'CL')['allowance'], $row($this->ravi, 'CL', 2027)['allowance']]);

        // Staff do not get the manager tabs or actions.
        Livewire::actingAs($this->ravi)->withQueryParams(['tab' => 'balances'])->test(Leave::class)->assertSet('tab', 'mine')->assertDontSee('Leave types')
            ->call('editBalance', $this->meera->id)->assertForbidden();
        Livewire::actingAs($this->ravi)->test(Leave::class)->call('editType')->assertForbidden();

        // A manager sets a custom CL figure, adds earned leave and a joining date for one person.
        $page = Livewire::actingAs($this->hr)->withQueryParams(['tab' => 'balances'])->test(Leave::class)->assertSee('Leave left for each person')->assertSee('Ravi Staff');
        $page->call('editBalance', $this->ravi->id)->assertSee('Leave balances for Ravi Staff')->assertSet('balanceForm.joined_on', '2025-06-01')
            ->set('balanceForm.rows.'.$type('CL')->id.'.days', '500')->call('saveBalance')->assertHasErrors('balanceForm.rows.'.$type('CL')->id.'.days')
            ->set('balanceForm.rows.'.$type('CL')->id.'.days', '15')->set('balanceForm.rows.'.$type('EL')->id.'.extra', '4.5')->set('balanceForm.rows.'.$type('EL')->id.'.note', 'Earned in 2026')
            ->call('saveBalance')->assertHasNoErrors();
        $this->assertSame([15.0, true, 4.5], [$row($this->ravi, 'CL')['allowance'], $row($this->ravi, 'CL')['custom'], $row($this->ravi, 'EL')['left']]);
        // Clearing the custom figure goes back to the automatic rule.
        $page->call('editBalance', $this->ravi->id)->set('balanceForm.rows.'.$type('CL')->id.'.days', '')->call('saveBalance')->assertHasNoErrors();
        $this->assertSame([12.0, false], [$row($this->ravi, 'CL')['allowance'], $row($this->ravi, 'CL')['custom']]);

        // Leave types: a manager changes the yearly figure and adds a new type.
        $types = Livewire::actingAs($this->hr)->withQueryParams(['tab' => 'types'])->test(Leave::class)->assertSee('Restricted Holiday')->assertSee('Half on 1 January, half on 1 July');
        $types->call('editType', $type('RH')->id)->set('typeForm.yearly_days', '3')->call('saveType')->assertHasNoErrors();
        $types->call('editType')->set('typeForm.name', 'Comp Off')->set('typeForm.code', 'CL')->set('typeForm.yearly_days', '1')->call('saveType')->assertHasErrors('typeForm.code')
            ->set('typeForm.code', 'co')->set('typeForm.accrual', 'manual')->call('saveType')->assertHasNoErrors();
        $this->assertSame([3.0, 0.0, 'CO'], [$row($this->ravi, 'RH')['allowance'], $row($this->ravi, 'CO')['allowance'], $type('CO')->code]);

        // Applying: the form shows what is left, more than the balance is refused, and waiting requests count.
        $apply = Livewire::actingAs($this->ravi)->test(Leave::class)->call('apply')->assertSee('RH · Restricted Holiday (3 remaining)')->assertSee('LOP · Loss of Pay (0 taken, not paid)');
        $apply->set('form.leave_type_id', (string) $type('ML')->id)->set('form.reason', 'Fever')->set('form.from_date', '2026-11-23')->set('form.to_date', '2026-11-25')->call('submit')->assertHasErrors('form.leave_type_id');
        $apply->set('form.to_date', '2026-11-24')->call('submit')->assertHasNoErrors();
        Livewire::actingAs($this->ravi)->test(Leave::class)->call('apply')->set('form.leave_type_id', (string) $type('ML')->id)->set('form.reason', 'Still unwell')
            ->set('form.from_date', '2026-11-26')->set('form.to_date', '2026-11-26')->set('form.day_part', 'first_half')->call('submit')->assertHasErrors('form.leave_type_id')
            // A quarter day is loss of pay only, and counts as 0.25.
            ->set('form.day_part', 'quarter')->set('form.leave_type_id', (string) $type('CL')->id)->call('submit')->assertHasErrors('form.leave_type_id')
            ->set('form.leave_type_id', (string) $type('LOP')->id)->call('submit')->assertHasNoErrors();
        $quarter = LeaveRequest::latest('id')->first();
        $this->assertSame(['0.25', '26 Nov 2026 (quarter day)', 'pending'], [$quarter->days, $quarter->period(), $quarter->status]);

        // A manager adds leave for someone: approved at once, the person is told, staff cannot do it.
        Livewire::actingAs($this->hr)->test(Leave::class)->call('apply')->assertSee('Leave for')->set('form.user_id', (string) $this->meera->id)
            ->set('form.leave_type_id', (string) $type('CL')->id)->set('form.reason', 'Informed by phone')->set('form.from_date', '2026-11-30')->set('form.to_date', '2026-12-01')->call('submit')->assertHasNoErrors();
        $added = LeaveRequest::where('user_id', $this->meera->id)->firstOrFail();
        $this->assertSame(['approved', $this->hr->id, '2.00'], [$added->status, $added->reviewed_by, $added->days]);
        Notification::assertSentTo($this->meera, LeaveDecided::class);
        $this->assertSame(16.0, $row($this->meera, 'CL')['left']);
        Livewire::actingAs($this->ravi)->test(Leave::class)->call('apply')->assertDontSee('Leave for')->set('form.user_id', (string) $this->meera->id)
            ->set('form.leave_type_id', (string) $type('LOP')->id)->set('form.reason', 'Sneaky')->set('form.from_date', '2026-12-07')->set('form.to_date', '2026-12-07')->call('submit')->assertHasNoErrors();
        $this->assertSame($this->ravi->id, LeaveRequest::latest('id')->first()->user_id);

        // The calendar: an Apply button that opens the form on the chosen day, and pending leave marked as waiting.
        Livewire::actingAs($this->ravi)->withQueryParams(['day' => '2026-11-27'])->test(\App\Livewire\Calendar\Index::class)->assertSee('Apply for leave')->assertSee('Apply for leave on this day')->assertSee('My leave · ML (waiting)');
        Livewire::actingAs($this->ravi)->withQueryParams(['apply' => 1, 'date' => '2026-11-27'])->test(Leave::class)->assertSet('showForm', true)->assertSet('form.from_date', '2026-11-27');
    }

    public function test_profile_has_details_and_devices(): void
    {
        $subAdmin = User::factory()->create(['name' => 'Sam Admin', 'role' => User::ROLE_SUB_ADMIN]);
        Equipment::create(['name' => 'MacBook Air', 'serial_number' => 'SN-RAVI-1', 'user_id' => $this->ravi->id, 'assigned_on' => '2026-01-05']);
        Equipment::create(['name' => 'Dell Monitor', 'user_id' => $this->meera->id]);

        // Everyone has their own profile, with their leave boxes; other people's are refused.
        $this->actingAs($this->ravi)->get('/profile')->assertOk()->assertSee('My Profile')->assertSee('Personal details')->assertSee('Apply for leave')->assertSee('Allocated Devices');
        $this->actingAs($this->ravi)->get('/profile/'.$this->meera->id)->assertForbidden();
        $this->actingAs($subAdmin)->get('/profile')->assertOk()->assertDontSee('Apply for leave');
        $this->actingAs($subAdmin)->get('/profile/'.$this->admin->id)->assertForbidden();
        // Super Admins open anyone's; Admins and leave managers open staff.
        $this->actingAs($this->admin)->get('/profile/'.$subAdmin->id)->assertOk()->assertSee('Sam Admin');
        $this->actingAs($subAdmin)->get('/profile/'.$this->ravi->id)->assertOk();
        $this->actingAs($this->hr)->get('/profile/'.$this->ravi->id)->assertOk()->assertSee('Back to Leave');
        $this->actingAs($this->hr)->get('/profile/'.$this->admin->id)->assertForbidden();

        // A person fills in their own details. The joining date is not theirs to set.
        $mine = Livewire::actingAs($this->ravi)->test(Profile::class)->call('editDetails', 'personal')->assertDontSee('Joined on</label>', false)
            ->set('form.personal_email', 'not-an-email')->set('form.blood_group', 'Z')->call('saveDetails')->assertHasErrors(['form.personal_email', 'form.blood_group'])
            ->set('form.personal_email', 'ravi@home.test')->set('form.blood_group', 'O+')->set('form.city', 'Kozhikode')->set('form.joined_on', '2020-01-01')->set('form.role', 'super_admin')
            ->call('saveDetails')->assertHasNoErrors()->assertSee('Kozhikode');
        $mine->call('editDetails', 'emergency')->set('form.emergency_name', 'Latha')->set('form.emergency_phone', '9000000001')->call('saveDetails')->assertHasNoErrors()->assertSee('Latha');
        $ravi = $this->ravi->fresh();
        $this->assertSame(['ravi@home.test', 'O+', 'Kozhikode', 'Latha', null, User::ROLE_STAFF], [$ravi->personal_email, $ravi->blood_group, $ravi->city, $ravi->emergency_name, $ravi->joined_on, $ravi->role]);
        // A manager sets the joining date on someone's profile.
        Livewire::actingAs($this->hr)->test(Profile::class, ['user' => $this->ravi])->call('editDetails', 'personal')->set('form.joined_on', '2024-03-01')->call('saveDetails')->assertHasNoErrors();
        $this->assertSame('2024-03-01', $this->ravi->fresh()->joined_on->toDateString());

        // Allocated devices: only that person's items.
        Livewire::actingAs($this->ravi)->withQueryParams(['tab' => 'devices'])->test(Profile::class)->assertSee('MacBook Air')->assertSee('SN-RAVI-1')->assertDontSee('Dell Monitor')->assertDontSee('Manage in Equipment');
        Livewire::actingAs($this->admin)->withQueryParams(['tab' => 'devices'])->test(Profile::class, ['user' => $this->meera])->assertSee('Dell Monitor')->assertSee('Manage in Equipment');
    }
}
