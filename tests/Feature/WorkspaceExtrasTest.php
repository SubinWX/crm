<?php

namespace Tests\Feature;

use App\Livewire\Finance\Subscriptions;
use App\Livewire\Office\Announcements;
use App\Livewire\Office\KnowledgeBase;
use App\Livewire\Search\Box;
use App\Livewire\Tasks\Board;
use App\Models\Announcement;
use App\Models\Client;
use App\Models\KbArticle;
use App\Models\RoadmapItem;
use App\Models\SoftwareSubscription;
use App\Models\Task;
use App\Models\User;
use App\Support\PendingItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceExtrasTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected User $ravi;

    protected User $meera;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-12 06:00:00');   // Thursday 12 Nov 2026
        $this->admin = User::factory()->superAdmin()->create(['name' => 'Asha Admin']);
        $this->ravi = User::factory()->create(['name' => 'Ravi Staff', 'role' => User::ROLE_STAFF, 'permissions' => ['projects.view']]);
        $this->meera = User::factory()->create(['name' => 'Meera Staff', 'role' => User::ROLE_STAFF, 'permissions' => ['tasks.manage']]);
    }

    public function test_the_task_board_shows_three_columns_and_moves_cards(): void
    {
        $client = Client::create(['name' => 'Kerala Traders']);
        $mine = Task::create(['title' => 'Design banner', 'client_id' => $client->id, 'status' => 'todo', 'due_date' => '2026-11-01']);
        $mine->assignTo([$this->ravi->id]);
        $other = Task::create(['title' => 'Write copy', 'client_id' => $client->id, 'status' => 'in_progress']);
        $other->assignTo([$this->meera->id]);
        $old = Task::create(['title' => 'Old finished job', 'client_id' => $client->id, 'status' => 'done']);
        $old->assignTo([$this->ravi->id]);
        Task::whereKey($old->id)->toBase()->update(['updated_at' => now()->subDays(30)]);

        $this->actingAs($this->ravi)->get('/tasks/board')->assertOk()->assertSee('Task Board')->assertSee('To do')->assertSee('In progress')->assertSee('Done');
        // "My tasks" by default; finished work older than two weeks is left to the list.
        $board = Livewire::actingAs($this->ravi)->test(Board::class)->assertSee('Design banner')->assertSee('Overdue')->assertDontSee('Write copy')->assertDontSee('Old finished job');
        $board->set('show', 'all')->assertSee('Write copy');

        // Someone moves their own card; not a colleague's unless they manage tasks.
        $board->call('move', $mine->id, 'in_progress');
        $this->assertSame('in_progress', $mine->fresh()->status);
        $board->call('move', $mine->id, 'nonsense')->assertStatus(422);
        Livewire::actingAs($this->ravi)->test(Board::class)->call('move', $other->id, 'done')->assertForbidden();
        Livewire::actingAs($this->meera)->test(Board::class)->call('move', $mine->id, 'done')->assertHasNoErrors();
        $this->assertSame(['done', 'in_progress'], [$mine->fresh()->status, $other->fresh()->status]);
    }

    public function test_announcements_show_on_the_dashboard_with_birthdays_and_anniversaries(): void
    {
        // Nothing to show: no notice board.
        $this->actingAs($this->ravi)->get('/')->assertOk()->assertDontSee('Notice board');

        Livewire::actingAs($this->ravi)->test(Announcements::class)->assertSee('No announcements yet')->assertDontSee('New announcement')->call('edit')->assertForbidden();
        $page = Livewire::actingAs($this->admin)->test(Announcements::class);
        $page->call('edit')->call('save')->assertHasErrors('form.title')
            ->set('form.title', 'Office closed on Friday')->set('form.body', '<div>For <strong>Onam</strong>.</div><script>x</script>')->set('form.pinned', true)->call('save')->assertHasNoErrors();
        $page->call('edit')->set('form.title', 'Old notice')->set('form.expires_on', '2026-11-01')->call('save')->assertHasNoErrors()->assertSee('Ended');
        $this->assertSame('<div>For <strong>Onam</strong>.</div>', Announcement::where('title', 'Office closed on Friday')->first()->body);

        // The dashboard shows current notices only.
        $this->actingAs($this->ravi)->get('/')->assertSee('Notice board')->assertSee('Office closed on Friday')->assertSee('For Onam.')->assertDontSee('Old notice');
        Livewire::actingAs($this->ravi)->test(Announcements::class)->call('delete', Announcement::first()->id)->assertForbidden();

        // Birthdays and work anniversaries in the next 7 days; a first-day joining date is not an anniversary.
        $this->meera->update(['date_of_birth' => '1995-11-14', 'joined_on' => '2024-11-12']);
        $this->ravi->update(['date_of_birth' => '1990-12-25', 'joined_on' => '2026-11-12']);
        $this->actingAs($this->ravi)->get('/')->assertSee('Meera Staff')->assertSee('Birthday')->assertSee('2 years with us')->assertSee('Today')->assertSee('Sat, 14 Nov')
            ->assertDontSee('0 years with us')->assertDontSee('1995');
    }

    public function test_subscriptions_track_cost_and_renewals_for_finance_only(): void
    {
        $this->actingAs($this->ravi)->get('/subscriptions')->assertForbidden();
        $this->actingAs($this->admin)->get('/subscriptions')->assertOk()->assertSee('No subscriptions here yet');

        $page = Livewire::actingAs($this->admin)->test(Subscriptions::class);
        $page->call('edit')->call('save')->assertHasErrors(['form.name', 'form.cost'])
            ->set('form.name', 'Google Workspace')->set('form.cost', '1200')->set('form.billing_cycle', 'yearly')->set('form.renews_on', '2026-11-15')
            ->set('form.owner_id', (string) $this->meera->id)->set('form.url', 'not a link')->call('save')->assertHasErrors('form.url')
            ->set('form.url', 'https://workspace.google.com')->call('save')->assertHasNoErrors();
        $page->call('edit')->set('form.name', 'Figma')->set('form.cost', '900')->set('form.billing_cycle', 'monthly')->set('form.renews_on', '2027-03-01')->call('save')->assertHasNoErrors();
        $page->call('edit')->set('form.name', 'Old tool')->set('form.cost', '5000')->set('form.status', 'cancelled')->call('save')->assertHasNoErrors();

        // Per month: 1200 / 12 + 900 = 1000; cancelled ones are not counted.
        $page->assertViewHas('monthly', 1000.0)->assertViewHas('yearly', 12000.0)->assertViewHas('renewingSoon', 1)->assertSee('Google Workspace')->assertSee('in 3 days')
            ->assertSee('Meera Staff')->assertDontSee('Old tool')->set('status', 'all')->assertSee('Old tool');

        // It appears in "Needs your attention" for finance, and marking it renewed moves the date on a year.
        $this->assertSame(1, array_column(PendingItems::for($this->admin), 'count', 'label')['subscription renewing within 7 days']);
        $google = SoftwareSubscription::where('name', 'Google Workspace')->firstOrFail();
        $page->call('renewed', $google->id);
        $this->assertSame('2027-11-15', $google->fresh()->renews_on->toDateString());
        $this->assertArrayNotHasKey('subscription renewing within 7 days', array_column(PendingItems::for($this->admin), 'count', 'label'));
        Livewire::actingAs($this->ravi)->test(Subscriptions::class)->assertForbidden();
    }

    public function test_the_knowledge_base_is_written_by_anyone_and_edited_by_its_author_or_a_super_admin(): void
    {
        $this->actingAs($this->ravi)->get('/knowledge-base')->assertOk()->assertSee('No articles yet');
        Livewire::actingAs($this->ravi)->test(KnowledgeBase::class)->call('edit')->call('save')->assertHasErrors(['form.title', 'form.body'])
            ->set('form.title', 'How we onboard a client')->set('form.category', 'Process')->set('form.body', '<div>Send the <strong>welcome kit</strong>.</div><script>x</script>')
            ->call('save')->assertHasNoErrors()->assertSee('How we onboard a client')->assertSee('welcome kit')->assertSee('Written by Ravi Staff');
        $article = KbArticle::firstOrFail();
        $this->assertSame(['<div>Send the <strong>welcome kit</strong>.</div>', $this->ravi->id], [$article->body, $article->created_by]);
        KbArticle::create(['title' => 'Leave rules', 'category' => 'HR', 'body' => '<div>Apply two days ahead.</div>', 'created_by' => $this->admin->id]);

        // Search and categories.
        Livewire::actingAs($this->meera)->test(KnowledgeBase::class)->assertSee('How we onboard a client')->assertSee('Leave rules')
            ->set('search', 'welcome')->assertSee('How we onboard a client')->assertDontSee('Leave rules')
            ->set('search', '')->set('category', 'HR')->assertSee('Leave rules')->assertDontSee('How we onboard a client');
        Livewire::actingAs($this->meera)->test(Box::class)->set('q', 'onboard')->assertSee('Knowledge base')->assertSee('How we onboard a client');

        // A colleague cannot change someone else's article; the author and a Super Admin can.
        Livewire::actingAs($this->meera)->test(KnowledgeBase::class)->call('edit', $article->id)->assertForbidden();
        Livewire::actingAs($this->meera)->test(KnowledgeBase::class)->call('delete', $article->id)->assertForbidden();
        Livewire::actingAs($this->admin)->test(KnowledgeBase::class)->call('edit', $article->id)->set('form.title', 'How we onboard a new client')->call('save')->assertHasNoErrors();
        $this->assertSame(['How we onboard a new client', $this->admin->id], [$article->fresh()->title, $article->fresh()->updated_by]);
        Livewire::actingAs($this->ravi)->test(KnowledgeBase::class)->call('delete', $article->id)->assertHasNoErrors();
        $this->assertNull($article->fresh());
    }

    public function test_the_timeline_lists_the_new_features(): void
    {
        $status = fn (string $title) => RoadmapItem::where('title', $title)->value('status');
        $this->assertSame(['done', 'done', 'done', 'done', 'planned', 'planned', 'planned'],
            [$status('Kanban board for tasks'), $status('Announcements'), $status('Software subscriptions tracker'), $status('Knowledge base'), $status('Recurring tasks'), $status('Goals (OKRs)'), $status('Automation rules')]);
        $this->assertSame(1, RoadmapItem::where('title', 'Announcements')->count());
    }
}
