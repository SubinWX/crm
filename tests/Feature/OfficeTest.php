<?php

namespace Tests\Feature;

use App\Livewire\Assistant\Index as AssistantPage;
use App\Livewire\Chat\Index as Chat;
use App\Livewire\Chat\Notifier;
use App\Livewire\Office\Documents;
use App\Livewire\Office\MeetingNotes;
use App\Livewire\Office\Policies;
use App\Livewire\Office\Roadmap;
use App\Models\AssistantMessage;
use App\Models\AttendanceRecord;
use App\Models\ChatMessage;
use App\Models\Client;
use App\Models\Document;
use App\Models\LeaveRequest;
use App\Models\Lookup;
use App\Models\MeetingNote;
use App\Models\Policy;
use App\Models\RoadmapItem;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Support\Assistant;
use App\Support\MailSettings;
use App\Support\PendingItems;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OfficeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected User $ravi;

    protected User $meera;

    protected User $hr;

    protected User $subAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-12 06:00:00');
        $this->admin = User::factory()->superAdmin()->create(['name' => 'Asha Admin']);
        $this->ravi = User::factory()->create(['name' => 'Ravi Staff', 'role' => User::ROLE_STAFF]);
        $this->meera = User::factory()->create(['name' => 'Meera Staff', 'role' => User::ROLE_STAFF]);
        $this->hr = User::factory()->create(['name' => 'Hari HR', 'role' => User::ROLE_STAFF, 'permissions' => ['team.manage']]);
        $this->subAdmin = User::factory()->create(['name' => 'Sam Admin', 'role' => User::ROLE_SUB_ADMIN]);
    }

    public function test_rules_and_policies_are_written_by_super_admins_and_read_by_everyone(): void
    {
        $this->actingAs($this->ravi)->get('/policies')->assertOk()->assertSee('No rules or policies have been added yet')->assertDontSee('Add policy');
        Livewire::actingAs($this->ravi)->test(Policies::class)->call('edit')->assertForbidden();
        Livewire::actingAs($this->admin)->test(Policies::class)->call('edit')->call('save')->assertHasErrors(['form.title', 'form.body'])
            ->set('form.title', 'Working hours')->set('form.body', '<div>Office time is <strong>9:30 to 6</strong>.</div><script>alert(1)</script>')->call('save')->assertHasNoErrors()->assertSee('Working hours');
        $policy = Policy::firstOrFail();
        $this->assertSame('<div>Office time is <strong>9:30 to 6</strong>.</div>', $policy->body);
        $this->actingAs($this->subAdmin)->get('/policies')->assertOk()->assertSee('Working hours')->assertSee('9:30 to 6');
        Livewire::actingAs($this->ravi)->test(Policies::class)->call('delete', $policy->id)->assertForbidden();
        // The profile no longer carries these tabs; they are in the Office menu.
        $this->actingAs($this->ravi)->get('/profile')->assertOk()->assertDontSee('Meeting Notes</button>', false)->assertSee('Rules &amp; Policies', false);
    }

    public function test_meeting_notes_are_written_by_managers_and_shared_notes_read_by_the_person(): void
    {
        Livewire::actingAs($this->ravi)->test(MeetingNotes::class)->assertSee('No meeting notes yet')->assertDontSee('Add meeting note')->call('edit')->assertForbidden();

        $page = Livewire::actingAs($this->admin)->test(MeetingNotes::class);
        $page->call('edit')->set('form.title', 'Quarterly review')->set('form.held_on', '2026-11-10')->set('form.notes', '<div>Doing well.</div>')->call('save')->assertHasErrors('form.user_id')
            ->set('form.user_id', (string) $this->admin->id)->call('save')->assertHasErrors('form.user_id')   // not about yourself
            ->set('form.user_id', (string) $this->ravi->id)->call('save')->assertHasNoErrors();
        $page->call('edit')->set('form.user_id', (string) $this->ravi->id)->set('form.title', 'Salary discussion')->set('form.notes', '<div>Private.</div>')->set('form.shared', false)->call('save')->assertHasNoErrors()
            ->assertSee('Salary discussion')->assertSee('Managers only');
        $private = MeetingNote::where('shared', false)->firstOrFail();
        $this->assertSame([$this->ravi->id, $this->admin->id], [$private->user_id, $private->created_by]);

        // The person reads the shared note only, and cannot change anything.
        Livewire::actingAs($this->ravi)->test(MeetingNotes::class)->assertSee('Quarterly review')->assertSee('Asha Admin')->assertDontSee('Salary discussion')
            ->call('delete', $private->id)->assertForbidden();
        Livewire::actingAs($this->meera)->test(MeetingNotes::class)->assertDontSee('Quarterly review');
        // Leave managers and Admins look after staff: they see and write staff notes, not notes about Super Admins.
        Livewire::actingAs($this->hr)->test(MeetingNotes::class)->assertSee('Salary discussion')->set('person', (string) $this->meera->id)->assertDontSee('Salary discussion')
            ->call('edit')->set('form.user_id', (string) $this->admin->id)->set('form.title', 'x')->set('form.notes', 'x')->call('save')->assertHasErrors('form.user_id');
        Livewire::actingAs($this->subAdmin)->test(MeetingNotes::class)->assertSee('Quarterly review')->call('delete', $private->id)->assertHasNoErrors();
        $this->assertSame(1, MeetingNote::count());
    }

    public function test_everyone_uploads_documents_and_only_the_right_people_open_them(): void
    {
        Storage::fake('local');
        $this->actingAs($this->ravi)->get('/documents')->assertOk()->assertSee('No documents found')->assertSee('Upload document');

        // Ravi uploads his own document. He cannot file it for someone else or publish it to everyone.
        $upload = Livewire::actingAs($this->ravi)->test(Documents::class)->call('create')->assertDontSee('everyone in the team can open it')
            ->call('save')->assertHasErrors(['form.name', 'form.category', 'file'])
            ->set('form.name', 'Aadhaar card')->set('form.category', 'identity')->set('file', UploadedFile::fake()->create('virus.exe', 10))->call('save')->assertHasErrors('file')
            ->set('file', UploadedFile::fake()->create('aadhaar.pdf', 120, 'application/pdf'))
            ->set('form.user_id', (string) $this->meera->id)->call('save')->assertHasErrors('form.user_id')
            ->set('form.user_id', (string) $this->ravi->id)->set('form.shared', true)->call('save')->assertHasNoErrors()->assertSee('Aadhaar card');
        $doc = Document::firstOrFail();
        $this->assertSame([$this->ravi->id, $this->ravi->id, false, 'aadhaar.pdf'], [$doc->user_id, $doc->added_by, $doc->shared, $doc->original_name]);
        Storage::disk('local')->assertExists($doc->path);

        // Who sees and downloads it: the owner, managers and Super Admins. Not a colleague.
        $this->actingAs($this->ravi)->get('/documents/'.$doc->id.'/download')->assertOk();
        $this->actingAs($this->hr)->get('/documents/'.$doc->id.'/download')->assertOk();
        $this->actingAs($this->admin)->get('/documents/'.$doc->id.'/download')->assertOk();
        $this->actingAs($this->meera)->get('/documents/'.$doc->id.'/download')->assertForbidden();
        Livewire::actingAs($this->meera)->test(Documents::class)->assertDontSee('Aadhaar card')->call('delete', $doc->id)->assertForbidden();

        // A Super Admin publishes a company document for everyone, and files one for a person.
        Livewire::actingAs($this->admin)->test(Documents::class)->call('create')->assertSee('everyone in the team can open it')
            ->set('form.name', 'Holiday list 2027')->set('form.category', 'company')->set('form.shared', true)->set('file', UploadedFile::fake()->create('holidays.pdf', 50, 'application/pdf'))->call('save')->assertHasNoErrors()
            ->call('create')->set('form.name', 'Offer letter')->set('form.category', 'employment')->set('form.user_id', (string) $this->meera->id)->set('file', UploadedFile::fake()->create('offer.pdf', 50, 'application/pdf'))->call('save')->assertHasNoErrors();
        $shared = Document::where('shared', true)->firstOrFail();
        $this->actingAs($this->meera)->get('/documents/'.$shared->id.'/download')->assertOk();
        Livewire::actingAs($this->meera)->test(Documents::class)->assertSee('Holiday list 2027')->assertSee('Offer letter')->assertDontSee('Aadhaar card')
            ->call('delete', $shared->id)->assertForbidden();

        // Filters, search and sorting.
        Livewire::actingAs($this->admin)->test(Documents::class)->assertSee('Showing 3 of 3 results')
            ->set('category', 'identity')->assertSee('Aadhaar card')->assertDontSee('Offer letter')
            ->set('category', '')->set('search', 'holiday')->assertSee('Holiday list 2027')->assertDontSee('Aadhaar card')
            ->set('search', '')->set('date', '2026-01-01')->assertSee('No documents found')
            ->set('date', '')->call('sortBy', 'name')->assertSet('dir', 'asc')->assertSeeInOrder(['Aadhaar card', 'Holiday list 2027', 'Offer letter']);

        // Deleting removes the file too.
        Livewire::actingAs($this->ravi)->test(Documents::class)->call('delete', $doc->id)->assertHasNoErrors();
        Storage::disk('local')->assertMissing($doc->path);
    }

    public function test_the_timeline_lists_done_and_planned_items_and_super_admins_keep_it(): void
    {
        // It starts with what was built and what is planned.
        $this->actingAs($this->ravi)->get('/timeline')->assertOk()->assertSee('Change log')->assertSee('Payroll and salary slips')->assertSee('Leave types and balances')->assertDontSee('Add item');
        Livewire::actingAs($this->ravi)->test(Roadmap::class)->call('edit')->assertForbidden();

        $page = Livewire::actingAs($this->admin)->test(Roadmap::class);
        $page->call('edit')->call('save')->assertHasErrors('form.title')->set('form.title', 'Mobile app')->set('form.area', 'General')->call('save')->assertHasNoErrors()->assertSee('Mobile app');
        $item = RoadmapItem::where('title', 'Mobile app')->firstOrFail();
        $this->assertSame('planned', $item->status);
        // Planned → in progress → done, dated today.
        $page->call('advance', $item->id);
        $this->assertSame('in_progress', $item->fresh()->status);
        $page->call('advance', $item->id);
        $this->assertSame(['done', '2026-11-12'], [$item->fresh()->status, $item->fresh()->happened_on->toDateString()]);
        Livewire::actingAs($this->ravi)->test(Roadmap::class)->call('delete', $item->id)->assertForbidden();
        Livewire::actingAs($this->admin)->test(Roadmap::class)->call('delete', $item->id);
        $this->assertNull($item->fresh());
    }

    public function test_chat_has_a_team_room_and_private_messages_with_unread_counts(): void
    {
        $this->actingAs($this->subAdmin)->get('/chat')->assertOk()->assertSee('Team room')->assertSee('Ravi Staff');

        // The team room is read by everyone.
        Livewire::actingAs($this->ravi)->test(Chat::class)->call('send')->assertHasErrors('body')->set('body', 'Good morning team')->call('send')->assertHasNoErrors()->assertSet('body', '')->assertSee('Good morning team');
        $this->assertSame([1, 0], [ChatMessage::unreadFor($this->meera), ChatMessage::unreadFor($this->ravi)]);
        Livewire::actingAs($this->meera)->test(Chat::class)->assertSee('Good morning team');
        $this->assertSame(0, ChatMessage::unreadFor($this->meera->fresh()));

        // A private message is seen by the two people only.
        Livewire::actingAs($this->ravi)->withQueryParams(['with' => (string) $this->meera->id])->test(Chat::class)->assertSee('only the two of you')->set('body', 'Lunch at one?')->call('send')->assertHasNoErrors();
        $this->assertSame([$this->ravi->id, $this->meera->id], [ChatMessage::latest('id')->first()->sender_id, ChatMessage::latest('id')->first()->recipient_id]);
        $this->assertSame([1, 0], [ChatMessage::unreadFor($this->meera->fresh()), ChatMessage::unreadFor($this->admin->fresh()) - 1]);   // admin: only the team-room message
        Livewire::actingAs($this->admin)->withQueryParams(['with' => (string) $this->ravi->id])->test(Chat::class)->assertDontSee('Lunch at one?');
        Livewire::actingAs($this->admin)->test(Chat::class)->assertDontSee('Lunch at one?');
        Livewire::actingAs($this->meera)->withQueryParams(['with' => (string) $this->ravi->id])->test(Chat::class)->assertSee('Lunch at one?');
        $this->assertSame(0, ChatMessage::unreadFor($this->meera->fresh()));

        // Attachments: a picture or a file, with or without text. A message needs one or the other.
        Storage::fake('local');
        Livewire::actingAs($this->ravi)->withQueryParams(['with' => (string) $this->meera->id])->test(Chat::class)
            ->set('file', UploadedFile::fake()->create('virus.exe', 10))->call('send')->assertHasErrors('file')
            ->call('removeFile')->call('send')->assertHasErrors('body')
            ->set('file', UploadedFile::fake()->image('screenshot.png', 400, 300))->assertSee('ready to send')->call('send')->assertHasNoErrors()->assertSet('file', null)->assertSee('screenshot.png');
        Livewire::actingAs($this->ravi)->withQueryParams([])->test(Chat::class)->assertSet('with', '')->set('body', 'The price list')->set('file', UploadedFile::fake()->create('prices.pdf', 80, 'application/pdf'))->call('send')->assertHasNoErrors()
            ->assertSee('prices.pdf')->assertSee('The price list');
        [$picture, $pdf] = [ChatMessage::where('attachment_name', 'screenshot.png')->firstOrFail(), ChatMessage::where('attachment_name', 'prices.pdf')->firstOrFail()];
        $this->assertSame([null, true, 'Sent a picture', false, 'The price list'], [$picture->body, $picture->isImage(), $picture->preview(), $pdf->isImage(), $pdf->preview()]);
        Storage::disk('local')->assertExists($picture->attachment_path);
        // A private attachment opens for its two people only; a team-room one for everyone.
        $this->actingAs($this->meera)->get('/chat/attachments/'.$picture->id)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->actingAs($this->admin)->get('/chat/attachments/'.$picture->id)->assertNotFound();
        $this->actingAs($this->admin)->get('/chat/attachments/'.$pdf->id)->assertOk()->assertDownload('prices.pdf');

        // The popup: a new message raises one on other pages, with a link to the conversation; none on the Chat page or for your own message.
        $notifier = Livewire::actingAs($this->admin)->test(Notifier::class);
        $notifier->call('check')->assertNotDispatched('chat-popup');
        ChatMessage::create(['sender_id' => $this->ravi->id, 'recipient_id' => $this->admin->id, 'body' => 'Are you free?']);
        ChatMessage::create(['sender_id' => $this->admin->id, 'recipient_id' => null, 'body' => 'My own message']);
        ChatMessage::create(['sender_id' => $this->ravi->id, 'recipient_id' => $this->hr->id, 'body' => 'Not for the admin']);
        $notifier->call('check')->assertDispatched('chat-popup', name: 'Ravi Staff', room: 'Private message', text: 'Are you free?', url: route('chat', ['with' => $this->ravi->id]))
            ->assertDispatched('chat-unread')->call('check')->assertNotDispatched('chat-popup', text: 'Not for the admin');
        ChatMessage::create(['sender_id' => $this->ravi->id, 'recipient_id' => $this->admin->id, 'body' => 'Second']);
        Livewire::actingAs($this->admin)->test(Notifier::class)->set('onChat', true)->call('check')->assertNotDispatched('chat-popup')->assertDispatched('chat-unread', count: 0);

        // Nobody messages themselves or a deactivated account: it falls back to the team room.
        $this->meera->update(['is_active' => false]);
        Livewire::actingAs($this->ravi)->withQueryParams(['with' => (string) $this->meera->id])->test(Chat::class)->assertSet('with', '');
        Livewire::actingAs($this->ravi)->withQueryParams(['with' => (string) $this->ravi->id])->test(Chat::class)->assertSet('with', '');
    }

    public function test_the_ai_assistant_is_off_until_a_key_is_saved_and_keeps_each_persons_conversation_private(): void
    {
        // Off: staff are told to ask a Super Admin; they cannot save a key or ask anything.
        $this->actingAs($this->ravi)->get('/assistant')->assertOk()->assertSee('not set up yet');
        Livewire::actingAs($this->ravi)->test(AssistantPage::class)->set('apiKey', 'sk-ant-'.str_repeat('a', 40))->call('saveKey')->assertForbidden();
        Livewire::actingAs($this->ravi)->test(AssistantPage::class)->set('question', 'Hello')->call('ask')->assertForbidden();

        // A Super Admin saves the key: stored encrypted, never sent back.
        Livewire::actingAs($this->admin)->test(AssistantPage::class)->assertSee('Setup')->set('apiKey', 'wrong')->call('saveKey')->assertHasErrors('apiKey')
            ->set('apiKey', 'sk-ant-'.str_repeat('a', 40))->call('saveKey')->assertHasNoErrors()->assertSet('apiKey', '');
        $this->assertTrue(Assistant::enabled());
        $this->assertNotSame('sk-ant-'.str_repeat('a', 40), Setting::get('assistant_api_key'));
        $this->assertSame('sk-ant-'.str_repeat('a', 40), MailSettings::secret('assistant_api_key'));

        // A stand-in for the real service records what would be sent.
        $fake = new class extends Assistant
        {
            public array $sent = [];

            public bool $fail = false;

            public function reply(array $messages): string
            {
                $this->sent[] = $messages;
                if ($this->fail) {
                    throw new \RuntimeException('Could not reach the assistant.');
                }

                return "Here is a **draft**.\n\n<script>alert(1)</script>";
            }
        };
        $this->app->instance(Assistant::class, $fake);

        $chat = Livewire::actingAs($this->ravi)->test(AssistantPage::class)->assertSee('How can I help?')->call('ask')->assertHasErrors('question')
            ->set('question', 'Write a payment reminder')->call('ask')->assertHasNoErrors()->assertSet('question', '')->assertSee('Write a payment reminder')->assertSee('Thinking…')
            ->call('answer')->assertSee('<strong>draft</strong>', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('Thinking…');
        $this->assertSame([['role' => 'user', 'content' => 'Write a payment reminder']], $fake->sent[0]);
        // The next question carries the conversation so far.
        $chat->set('question', 'Make it shorter')->call('ask')->call('answer');
        $this->assertSame(['user', 'assistant', 'user'], array_column($fake->sent[1], 'role'));
        $this->assertSame(4, AssistantMessage::where('user_id', $this->ravi->id)->count());

        // A failure shows a message and the unanswered question is not sent twice.
        $fake->fail = true;
        $chat->set('question', 'Third question')->call('ask')->call('answer')->assertSee('Could not reach the assistant.');
        $fake->fail = false;
        $chat->set('question', 'Third question again')->call('ask')->call('answer')->assertHasNoErrors();
        $this->assertSame(['user', 'assistant', 'user', 'assistant', 'user'], array_column(end($fake->sent), 'role'));

        // Conversations are private, and "New conversation" clears only your own.
        Livewire::actingAs($this->meera)->test(AssistantPage::class)->assertDontSee('Write a payment reminder')->set('question', 'Hi')->call('ask')->call('answer');
        Livewire::actingAs($this->ravi)->test(AssistantPage::class)->assertSee('Write a payment reminder')->call('clear')->assertSee('How can I help?');
        $this->assertSame([0, 2], [AssistantMessage::where('user_id', $this->ravi->id)->count(), AssistantMessage::where('user_id', $this->meera->id)->count()]);

        // Removing the key switches it off for everyone.
        Livewire::actingAs($this->ravi)->test(AssistantPage::class)->call('removeKey')->assertForbidden();
        Livewire::actingAs($this->admin)->test(AssistantPage::class)->call('removeKey');
        $this->assertFalse(Assistant::enabled());
    }

    public function test_the_dashboard_highlights_what_is_waiting_for_each_person(): void
    {
        // Thursday 12 Nov 2026, a working day: nobody has checked in yet.
        $this->actingAs($this->ravi)->get('/')->assertOk()->assertSee('Needs your attention')->assertSee('You have not checked in today')->assertDontSee('leave request to approve');

        $client = Client::create(['name' => 'Kerala Traders']);
        $task = Task::create(['title' => 'Send banner', 'client_id' => $client->id, 'status' => 'open', 'due_date' => '2026-11-01']);
        $task->assignTo([$this->ravi->id]);
        Task::create(['title' => 'Later task', 'client_id' => $client->id, 'status' => 'open', 'due_date' => '2026-12-01'])->assignTo([$this->ravi->id]);
        $type = Lookup::where('type', 'leave_type')->where('code', 'CL')->firstOrFail();
        LeaveRequest::create(['user_id' => $this->ravi->id, 'leave_type_id' => $type->id, 'from_date' => '2026-11-20', 'to_date' => '2026-11-20', 'days' => 1, 'day_part' => 'full', 'reason' => 'x', 'status' => 'pending']);
        ChatMessage::create(['sender_id' => $this->admin->id, 'recipient_id' => $this->ravi->id, 'body' => 'Hi']);
        AttendanceRecord::create(['user_id' => $this->ravi->id, 'date' => '2026-11-12', 'status' => 'present', 'check_in' => '09:30:00']);

        $labels = fn (User $u) => array_column(PendingItems::for($u->fresh()), 'count', 'label');
        // Staff: their own things only, and the check-in reminder is gone once they are in.
        $this->assertSame(['task of yours is overdue' => 1, 'unread chat message' => 1, 'leave request of yours waiting for approval' => 1, 'open task assigned to you' => 1], $labels($this->ravi));
        $this->actingAs($this->ravi)->get('/')->assertSee('task of yours is overdue')->assertSee('unread chat message')->assertDontSee('You have not checked in today');
        // A manager is told about the request to approve; a Super Admin as well.
        $this->assertSame(1, $labels($this->hr)['leave request to approve']);
        $this->assertArrayNotHasKey('leave request of yours waiting for approval', $labels($this->hr));
        $this->actingAs($this->admin)->get('/')->assertSee('leave request to approve');
        // Admins have a small dashboard; it shows the panel too, limited to what they can open.
        $this->actingAs($this->subAdmin)->get('/')->assertOk()->assertSee('You are all caught up');
        ChatMessage::create(['sender_id' => $this->admin->id, 'recipient_id' => null, 'body' => 'Team update']);
        $this->actingAs($this->subAdmin)->get('/')->assertSee('Needs your attention')->assertSee('unread chat message')->assertDontSee('checked in');

        // On a weekly off nobody is asked to check in.
        $this->travelTo('2026-11-15 06:00:00');   // Sunday
        $this->assertArrayNotHasKey('You have not checked in today', $labels($this->meera));
    }
}
