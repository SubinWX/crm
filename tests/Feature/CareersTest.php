<?php

namespace Tests\Feature;

use App\Livewire\Careers\Apply;
use App\Livewire\Careers\Listing;
use App\Livewire\Careers\Manage;
use App\Livewire\Dashboard;
use App\Models\Client;
use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Lookup;
use App\Models\User;
use App\Notifications\JobApplicationConfirmation;
use App\Notifications\JobApplicationReceived;
use App\Support\ApplicationFilters;
use App\Support\CalendarEvents;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CareersTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create(['name' => 'Asha Admin']);
    }

    protected function vacancy(array $attributes = []): JobVacancy
    {
        $title = $attributes['title'] ?? 'Laravel Developer';

        return JobVacancy::create($attributes + [
            'title' => $title, 'slug' => JobVacancy::uniqueSlug($title), 'status' => 'open',
            'description' => 'Build and maintain client web applications with the team.',
        ]);
    }

    protected function details(array $overrides = []): array
    {
        return $overrides + [
            'form.name' => 'Meera Nair', 'form.email' => 'Meera@Example.com', 'form.phone' => '+91 98765 43210',
            'form.experience_years' => '3.5', 'form.location' => 'Kochi', 'consent' => true,
        ];
    }

    public function test_vacancies_are_managed_by_the_team_and_only_open_ones_are_public(): void
    {
        $page = Livewire::actingAs($this->admin)->test(Manage::class);
        $page->call('createVacancy')->set('form.title', 'Laravel Developer')->set('form.description', 'short')->call('saveVacancy')->assertHasErrors('form.description');
        $page->set('form.description', 'Build and maintain client web applications.')->set('form.location', 'Kochi')
            ->set('form.department', 'Development')->set('form.status', 'draft')->call('saveVacancy')->assertHasNoErrors();

        $vacancy = JobVacancy::firstOrFail();
        $this->assertSame(['laravel-developer', 'draft', $this->admin->id], [$vacancy->slug, $vacancy->status, $vacancy->created_by]);
        // A second job with the same title, or one that would take a reserved address, gets its own address.
        $this->assertSame('laravel-developer-2', JobVacancy::uniqueSlug('Laravel Developer'));
        $this->assertSame('general-application-2', JobVacancy::uniqueSlug('General Application'));

        // Drafts are invisible to the public.
        auth()->logout();
        $this->get('/careers')->assertOk()->assertDontSee('Laravel Developer')->assertSee('No open positions right now');
        $this->get('/careers/laravel-developer')->assertNotFound();

        Livewire::actingAs($this->admin)->test(Manage::class)->call('setVacancyStatus', $vacancy->id, 'open');
        auth()->logout();
        $this->get('/careers')->assertOk()->assertSee('Laravel Developer')->assertSee('Kochi');
        $this->get('/careers/laravel-developer')->assertOk()->assertSee('Apply for this position')->assertSee('Build and maintain client web applications.');
        $this->get('/careers/general-application')->assertOk()->assertSee('Send your resume');
        Livewire::test(Listing::class)->set('search', 'designer')->assertSee('No positions match')->set('search', 'kochi')->assertSee('Laravel Developer');

        // Past its closing date, or closed: off the list, and the page says so instead of showing the form.
        $vacancy->update(['closes_on' => today_local()->subDay()->toDateString()]);
        $this->get('/careers')->assertDontSee('Laravel Developer');
        $this->get('/careers/laravel-developer')->assertOk()->assertSee('no longer open')->assertDontSee('Submit application');
        Livewire::test(Apply::class, ['slug' => 'laravel-developer'])->set($this->details())
            ->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))->call('submit')->assertHasErrors('form.name');
        $this->assertSame(0, JobApplication::count());

        // Editing keeps the public address; deleting keeps nothing public.
        Livewire::actingAs($this->admin)->test(Manage::class)->call('editVacancy', $vacancy->id)
            ->assertSet('form.title', 'Laravel Developer')->set('form.title', 'Senior Laravel Developer')->set('form.closes_on', '')->call('saveVacancy')->assertHasNoErrors();
        $this->assertSame(['Senior Laravel Developer', 'laravel-developer', null], [$vacancy->refresh()->title, $vacancy->slug, $vacancy->closes_on]);
    }

    public function test_someone_can_apply_with_a_resume_and_the_team_is_told(): void
    {
        Storage::fake('local');
        Notification::fake();
        $hr = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => ['careers.manage']]);
        $plain = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => ['tasks.manage']]);
        $vacancy = $this->vacancy();

        $form = Livewire::test(Apply::class, ['slug' => $vacancy->slug]);

        // Required details, a resume of the right kind, safe links and consent are all enforced.
        $form->call('submit')->assertHasErrors(['form.name', 'form.email', 'form.phone', 'resume', 'consent']);
        $form->set($this->details(['consent' => false]))->set('resume', UploadedFile::fake()->create('cv.exe', 100))->set('form.portfolio_url', 'javascript:alert(1)')
            ->call('submit')->assertHasErrors(['resume', 'consent', 'form.portfolio_url'])->assertHasNoErrors(['form.name', 'form.email']);
        $this->assertSame(0, JobApplication::count());

        $form->set('consent', true)->set('form.portfolio_url', 'https://meera.example.com')->set('form.cover_letter', 'I have shipped several Laravel projects.')
            ->set('resume', UploadedFile::fake()->create('Meera CV.pdf', 300, 'application/pdf'))
            ->call('submit')->assertHasNoErrors()->assertSet('submitted', true)->assertSee('Application received');

        $application = JobApplication::firstOrFail();
        $this->assertSame(['Meera Nair', 'meera@example.com', $vacancy->id, 'Laravel Developer', 'new', 'Meera CV.pdf'],
            [$application->name, $application->email, $application->job_vacancy_id, $application->vacancy_title, $application->status, $application->resume_name]);
        $this->assertSame('3.5', $application->experience_years);
        $this->assertStringStartsWith('resumes/', $application->resume_path);
        Storage::disk('local')->assertExists($application->resume_path);

        Notification::assertSentTo([$this->admin, $hr], JobApplicationReceived::class);
        Notification::assertNotSentTo($plain, JobApplicationReceived::class);
        Notification::assertSentTo(new AnonymousNotifiable, JobApplicationConfirmation::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'meera@example.com');
        $this->assertStringContainsString('Meera Nair applied for Laravel Developer', (new JobApplicationReceived($application))->message());
        $this->assertStringContainsString('We received your application', (new JobApplicationConfirmation($application))->toMail(new AnonymousNotifiable)->subject);

        // The same email cannot apply twice for the same job, but can for another or as a general application.
        Livewire::test(Apply::class, ['slug' => $vacancy->slug])->set($this->details())
            ->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))->call('submit')->assertHasErrors('form.email');
        Livewire::test(Apply::class)->set($this->details())
            ->set('resume', UploadedFile::fake()->create('cv.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
            ->call('submit')->assertHasNoErrors()->assertSet('submitted', true);
        $general = JobApplication::latest('id')->first();
        $this->assertSame([null, Apply::GENERAL_TITLE], [$general->job_vacancy_id, $general->vacancy_title]);

        // Bots that fill the hidden field see success, but nothing is stored.
        Livewire::test(Apply::class, ['slug' => $vacancy->slug])->set($this->details(['form.email' => 'bot@example.com']))->set('website', 'http://spam.example')
            ->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))->call('submit')->assertSet('submitted', true);
        $this->assertSame(2, JobApplication::count());

        // One connection is limited per hour.
        RateLimiter::clear('job-application|127.0.0.1');
        foreach (range(1, Apply::HOURLY_LIMIT) as $i) {
            Livewire::test(Apply::class, ['slug' => $vacancy->slug])->set($this->details(['form.email' => "person{$i}@example.com"]))
                ->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))->call('submit')->assertHasNoErrors();
        }
        Livewire::test(Apply::class, ['slug' => $vacancy->slug])->set($this->details(['form.email' => 'onemore@example.com']))
            ->set('resume', UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'))->call('submit')->assertHasErrors('form.name');
        $this->assertSame(2 + Apply::HOURLY_LIMIT, JobApplication::count());
    }

    public function test_applications_are_reviewed_filtered_sorted_exported_and_kept_private(): void
    {
        Storage::fake('local');
        $this->travelTo('2026-11-12 06:00:00'); // 11:30 IST
        $dev = $this->vacancy();
        $design = $this->vacancy(['title' => 'UI Designer']);

        $make = function (string $name, JobVacancy $vacancy, string $appliedAt, array $extra = []) {
            $path = UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf')->store('resumes', 'local');
            $application = JobApplication::create($extra + [
                'job_vacancy_id' => $vacancy->id, 'vacancy_title' => $vacancy->title, 'name' => $name,
                'email' => str($name)->slug().'@example.com', 'phone' => '9876543210', 'resume_path' => $path, 'resume_name' => $name.' CV.pdf',
            ]);
            $application->forceFill(['created_at' => $appliedAt])->saveQuietly();

            return $application;
        };
        $anu = $make('Anu Thomas', $dev, '2026-11-10 05:00:00', ['experience_years' => 2, 'rating' => 3]);
        $bala = $make('Bala Menon', $dev, '2026-11-11 20:00:00', ['experience_years' => 6, 'rating' => 5]); // 01:30 IST on the 12th
        $chitra = $make('Chitra Rao', $design, '2026-11-12 05:00:00', ['experience_years' => 4]);

        $names = fn (array $filters = [], ?string $sort = null) => ApplicationFilters::sort(ApplicationFilters::apply(JobApplication::query(), $filters), $sort)->pluck('name')->all();
        $this->assertSame(['Chitra Rao', 'Bala Menon', 'Anu Thomas'], $names());
        $this->assertSame(['Anu Thomas', 'Bala Menon', 'Chitra Rao'], $names([], 'oldest'));
        $this->assertSame(['Anu Thomas', 'Bala Menon', 'Chitra Rao'], $names([], 'name'));
        $this->assertSame(['Bala Menon', 'Anu Thomas', 'Chitra Rao'], $names([], 'rating'));
        $this->assertSame(['Bala Menon', 'Chitra Rao', 'Anu Thomas'], $names([], 'experience'));
        $this->assertSame(['Bala Menon', 'Anu Thomas'], $names(['vacancy' => $dev->id]));
        // Dates are the business day (IST), not the UTC day the row was stored on.
        $this->assertSame(['Chitra Rao', 'Bala Menon'], $names(['from' => '2026-11-12', 'to' => '2026-11-12']));
        $this->assertSame(['Anu Thomas'], $names(['to' => '2026-11-11']));
        $this->assertSame(['Chitra Rao'], $names(['search' => 'chitra']));

        // Overview: only the last 3 business days are listed; the chart counts by business day.
        $make('Deepa Old', $design, '2026-11-05 05:00:00');
        $overview = Livewire::actingAs($this->admin)->test(Manage::class);
        $overview->assertSet('tab', 'overview')->assertSee('Last 3 days')->assertSee('Anu Thomas')->assertSee('Chitra Rao')->assertDontSee('Deepa Old')
            ->assertViewHas('recent', fn ($recent) => $recent->pluck('name')->all() === ['Chitra Rao', 'Bala Menon', 'Anu Thomas'])
            ->assertViewHas('chart', fn ($chart) => $chart['total'] === 4 && $chart['bars']->count() === 30
                && $chart['bars']->last() === ['count' => 2, 'title' => 'Thu, 12 Nov 2026', 'label' => '12 Nov'] // Bala counts on the 12th in IST
                && $chart['byVacancy']->all() === ['Laravel Developer' => 2, 'UI Designer' => 2]);
        $overview->set('chartRange', '7')->assertViewHas('chart', fn ($chart) => $chart['total'] === 3 && $chart['bars']->count() === 7 && $chart['bars']->sum('count') === 3);
        $overview->set('chartRange', '12m')->assertViewHas('chart', fn ($chart) => $chart['bars']->count() === 12 && $chart['bars']->last()['count'] === 4 && $chart['bars']->last()['label'] === 'Nov');
        JobApplication::where('name', 'Deepa Old')->delete();
        foreach (['careers.manage' => 'Last 3 days', 'careers.applications' => 'Export CSV', 'careers.vacancies' => 'Copy link'] as $route => $text) {
            $this->actingAs($this->admin)->get(route($route))->assertOk()->assertSee($text)->assertSee('All Applications');
        }

        $page = Livewire::actingAs($this->admin)->test(Manage::class, ['tab' => 'applications']);
        $page->assertSee('Anu Thomas')->assertSee('Chitra Rao')->set('vacancy', (string) $design->id)->assertDontSee('Anu Thomas')->assertSee('Chitra Rao')
            ->call('clearFilters')->call('setRange', 'today')->assertSet('from', '2026-11-12')->assertDontSee('Anu Thomas')->assertSee('Bala Menon');

        // Opening a new application marks it as being reviewed; the review is saved with an interview time in IST.
        $page->call('view', $anu->id)->assertSet('review.status', 'reviewing')
            ->set('review.status', 'interview')->set('review.rating', '4')->set('review.interview_at', '2026-11-15T10:30')->set('review.notes', 'Strong portfolio.')
            ->call('saveReview')->assertHasNoErrors()->assertSet('viewingId', null);
        $anu->refresh();
        $this->assertSame(['interview', 4, 'Strong portfolio.', $this->admin->id], [$anu->status, $anu->rating, $anu->notes, $anu->reviewed_by]);
        $this->assertSame('2026-11-15 05:00:00', $anu->interview_at->utc()->format('Y-m-d H:i:s'));

        // The interview and a vacancy closing date show on the calendar for people who manage careers.
        $design->update(['closes_on' => '2026-11-20']);
        $events = CalendarEvents::between($this->admin, today_local()->startOfMonth(), today_local()->endOfMonth(), ['careers']);
        $this->assertSame(['2026-11-15' => 'Interview: Anu Thomas', '2026-11-20' => 'Vacancy closes: UI Designer'], $events->pluck('title', 'date')->all());
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'permissions' => ['tasks.manage']]);
        $this->assertNotContains('careers', CalendarEvents::allowedTypes($staff));

        // Notification links open the application directly.
        Livewire::actingAs($this->admin)->withQueryParams(['open' => $bala->id])->test(Manage::class, ['tab' => 'applications'])->assertSet('viewingId', $bala->id)->assertSee('Download resume');

        // Resume download and CSV export for the team; nobody else gets in.
        $download = $this->actingAs($this->admin)->get(route('careers.resume', $anu));
        $download->assertOk();
        $this->assertStringContainsString('anu-thomas-resume.pdf', $download->headers->get('content-disposition'));
        // "View" shows a real PDF in the browser tab; Word files, or anything merely named .pdf, are downloaded instead.
        Storage::disk('local')->put($anu->resume_path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");
        $view = $this->actingAs($this->admin)->get(route('careers.resume.view', $anu))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('inline;', $view->headers->get('content-disposition'));
        Storage::disk('local')->put($bala->resume_path, '<html><script>alert(1)</script></html>');
        $this->assertStringStartsWith('attachment;', $this->actingAs($this->admin)->get(route('careers.resume.view', $bala))->assertOk()->headers->get('content-disposition'));
        $chitra->update(['resume_name' => 'Chitra CV.docx']);
        $this->assertFalse($chitra->resumeIsPdf());
        $this->assertStringStartsWith('attachment;', $this->actingAs($this->admin)->get(route('careers.resume.view', $chitra))->assertOk()->headers->get('content-disposition'));
        Livewire::actingAs($this->admin)->test(Manage::class, ['tab' => 'applications'])->assertSee(route('careers.resume.view', $anu), false)->assertDontSee(route('careers.resume.view', $chitra), false);

        // The main dashboard shows a pie chart of applications by status to people who manage careers.
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('Careers: applications by status');
        $expected = JobApplication::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->map(fn ($n) => (int) $n);
        $this->assertSame(3, $expected->sum());
        Livewire::actingAs($this->admin)->test(Dashboard::class)->assertViewHas('careers', fn ($c) => $c['total'] === 3 && $c['openVacancies'] === 2
            && $c['slices']->pluck('count', 'status')->filter()->sortKeys()->all() === $expected->sortKeys()->all()
            && abs($c['slices']->sum('percent') - 100) < 0.5 && $c['slices']->first()['offset'] === 0
            && $c['slices']->last()['offset'] + $c['slices']->last()['percent'] === $c['slices']->sum('percent'));

        $csv = $this->actingAs($this->admin)->get(route('careers.export', ['vacancy' => $dev->id, 'sort' => 'name']));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('Anu Thomas', $body);
        $this->assertStringContainsString('Strong portfolio.', $body);
        $this->assertStringNotContainsString('Chitra Rao', $body);
        $this->assertLessThan(strpos($body, 'Bala Menon'), strpos($body, 'Anu Thomas'));

        $sub = User::factory()->create(['role' => User::ROLE_SUB_ADMIN]);
        foreach ([$staff, $sub] as $user) {
            $this->actingAs($user)->get(route('careers.manage'))->assertForbidden();
            $this->actingAs($user)->get(route('careers.applications'))->assertForbidden();
            $this->actingAs($user)->get(route('careers.vacancies'))->assertForbidden();
            $this->actingAs($user)->get(route('careers.resume', $anu))->assertForbidden();
            $this->actingAs($user)->get(route('careers.resume.view', $anu))->assertForbidden();
            $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('Careers: applications by status');
            $this->actingAs($user)->get(route('careers.export'))->assertForbidden();
            $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('recruitment');
            Livewire::actingAs($user)->test(Manage::class)->assertForbidden();
        }
        auth()->logout();
        $this->get(route('careers.resume', $anu))->assertRedirect();
        $this->get(route('careers.manage'))->assertRedirect();

        // Deleting a vacancy keeps its applications; deleting an application removes the resume file.
        Livewire::actingAs($this->admin)->test(Manage::class)->call('deleteVacancy', $dev->id);
        $this->assertSame([null, 'Laravel Developer'], [$anu->refresh()->job_vacancy_id, $anu->vacancy_title]);
        Livewire::actingAs($this->admin)->test(Manage::class)->call('deleteApplication', $anu->id);
        $this->assertNull(JobApplication::find($anu->id));
        Storage::disk('local')->assertMissing($anu->resume_path);
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('Careers');
    }

    public function test_editor_text_is_cleaned_and_shown_with_its_formatting(): void
    {
        $unsafe = '<div><strong>Plan</strong> campaigns<script>alert(1)</script><img src="x" onerror="alert(1)"></div>'
            .'<ul><li onclick="x()">Meta Ads</li><li>Google Ads</li></ul><a href="javascript:alert(1)">bad link</a> <a href="https://example.com/work" style="color:red">our work</a>';

        Livewire::actingAs($this->admin)->test(Manage::class, ['tab' => 'vacancies'])->call('createVacancy')
            ->set('form.title', 'Marketing Executive')->set('form.description', $unsafe)
            ->set('form.requirements', '<ul><li>Good communication</li></ul>')->call('saveVacancy')->assertHasNoErrors();

        $vacancy = JobVacancy::firstOrFail();
        $this->assertSame('<div><strong>Plan</strong> campaigns</div><ul><li>Meta Ads</li><li>Google Ads</li></ul>bad link <a href="https://example.com/work" target="_blank" rel="noopener noreferrer nofollow">our work</a>', $vacancy->description);

        // The public page shows the formatting, and nothing that could run.
        auth()->logout();
        $page = $this->get($vacancy->publicUrl())->assertOk();
        $page->assertSee('<strong>Plan</strong> campaigns', false)->assertSee('<li>Meta Ads</li>', false)->assertSee('<ul><li>Good communication</li></ul>', false)
            ->assertDontSee('<script>alert(1)', false)->assertDontSee('onerror', false)->assertDontSee('javascript:', false);

        // The editor reopens with the saved text; older plain text keeps its line breaks and is escaped.
        Livewire::actingAs($this->admin)->test(Manage::class, ['tab' => 'vacancies'])->call('editVacancy', $vacancy->id)
            ->assertSet('form.requirements', '<ul><li>Good communication</li></ul>')->assertSee('trix-editor', false);
        $this->assertSame("Line one<br />\nA &amp; B &lt;3", RichText::render("Line one\nA & B <3"));
        $this->assertSame('<div>Line one<br>A &amp; B</div>', RichText::forEditor("Line one\nA & B"));
        $this->assertSame("Plan campaigns\n• Meta Ads\n• Google Ads\nbad link our work", plain_text($vacancy->description));
        $this->assertNull(RichText::clean('<div><br></div>'));
        $this->assertStringContainsString('<a href="https://a.example"', RichText::render('See https://a.example', autolink: true));

        // Other editor fields are cleaned the same way when saved.
        $client = Client::create(['name' => 'Acme', 'notes' => '<p onclick="x()">Prefers <em>WhatsApp</em></p><iframe src="https://x.example"></iframe>']);
        $this->assertSame('<p>Prefers <em>WhatsApp</em></p>', $client->fresh()->notes);
        $this->actingAs($this->admin)->get(route('clients.show', $client))->assertOk()->assertSee('<p>Prefers <em>WhatsApp</em></p>', false);
    }

    public function test_departments_are_a_dropdown_that_can_be_added_to(): void
    {
        $this->assertContains('SEO', Lookup::ofType('job_department')->pluck('name')->all());

        $form = Livewire::actingAs($this->admin)->test(Manage::class, ['tab' => 'vacancies'])->call('createVacancy')
            ->assertSee('+ Add a new department')->set('form.title', 'Video Editor')->set('form.description', 'Edit short videos for client campaigns.');

        // Choosing "add new" needs a name; saving adds it to the list for next time.
        $form->set('form.department', '__new')->assertSee('New department name')->call('saveVacancy')->assertHasErrors('newDepartment');
        $form->set('newDepartment', ' Video Production ')->call('saveVacancy')->assertHasNoErrors();
        $this->assertSame('Video Production', JobVacancy::firstOrFail()->department);
        $this->assertSame(1, Lookup::where('type', 'job_department')->where('name', 'Video Production')->count());

        // Typing one that already exists (any capitals) reuses it; picking from the list just saves it.
        Livewire::actingAs($this->admin)->test(Manage::class, ['tab' => 'vacancies'])->call('createVacancy')
            ->set('form.title', 'SEO Analyst')->set('form.description', 'Improve search rankings for clients.')
            ->set('form.department', '__new')->set('newDepartment', 'seo')->call('saveVacancy')->assertHasNoErrors();
        $this->assertSame('SEO', JobVacancy::where('title', 'SEO Analyst')->value('department'));
        $this->assertSame(1, Lookup::where('type', 'job_department')->whereRaw('lower(name) = ?', ['seo'])->count());

        // Renaming a department in Settings renames it on the vacancies using it.
        $lookup = Lookup::where('type', 'job_department')->where('name', 'Video Production')->firstOrFail();
        $this->assertSame(1, $lookup->usageCount());
        $lookup->update(['name' => 'Video']);
        $this->assertSame('Video', JobVacancy::where('title', 'Video Editor')->value('department'));

        // A department later switched off stays selected on the vacancy that already uses it.
        $lookup->update(['is_active' => false]);
        Livewire::actingAs($this->admin)->test(Manage::class, ['tab' => 'vacancies'])->call('editVacancy', JobVacancy::where('title', 'Video Editor')->value('id'))
            ->assertSet('form.department', 'Video')->assertViewHas('departments', fn ($list) => $list->contains('Video'))
            ->call('saveVacancy')->assertHasNoErrors();
        Livewire::actingAs($this->admin)->test(Manage::class, ['tab' => 'vacancies'])->call('createVacancy')
            ->assertViewHas('departments', fn ($list) => ! $list->contains('Video') && $list->contains('SEO'));
    }
}
