<?php

namespace App\Livewire\Careers;

use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\User;
use App\Notifications\JobApplicationConfirmation;
use App\Notifications\JobApplicationReceived;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Public page for one vacancy (/careers/{slug}) with its application form, or the
 * general "send your resume" form (/careers/general-application). No sign-in needed.
 */
#[Layout('components.layouts.careers')]
class Apply extends Component
{
    use WithFileUploads;

    public const GENERAL_TITLE = 'General application';

    /** Applications one address may send per hour. */
    public const HOURLY_LIMIT = 5;

    #[Locked]
    public ?int $vacancyId = null;

    public array $form = [
        'name' => '', 'email' => '', 'phone' => '', 'location' => '', 'experience_years' => '', 'current_company' => '',
        'current_salary' => '', 'expected_salary' => '', 'notice_period' => '', 'portfolio_url' => '', 'linkedin_url' => '',
        'cover_letter' => '',
    ];

    public $resume;

    public bool $consent = false;

    /** Hidden from people; only automated form-fillers complete it. */
    public string $website = '';

    public bool $submitted = false;

    public function mount(?string $slug = null): void
    {
        if ($slug !== null) {
            // Drafts are never public. A closed vacancy still shows a "no longer open" page.
            $this->vacancyId = JobVacancy::where('slug', $slug)->where('status', '!=', 'draft')->firstOrFail()->id;
        }
    }

    /** Everyone who should hear about a new application. */
    public static function team(): Collection
    {
        return User::query()->active()->whereNotNull('password')
            ->where(fn ($q) => $q->where('role', User::ROLE_SUPER_ADMIN)
                ->orWhere(fn ($q) => $q->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUB_ADMIN])->whereJsonContains('permissions', 'careers.manage')))
            ->get();
    }

    public function submit(): void
    {
        $vacancy = $this->vacancyId ? JobVacancy::find($this->vacancyId) : null;
        if ($this->vacancyId && ! $vacancy?->isAccepting()) {
            $this->addError('form.name', 'Sorry, this position is no longer accepting applications.');

            return;
        }

        $this->validate([
            'form.name' => ['required', 'string', 'min:2', 'max:120'],
            'form.email' => ['required', 'email', 'max:190'],
            'form.phone' => ['required', 'string', 'min:7', 'max:25', 'regex:/^[0-9+()\-\s]+$/'],
            'form.location' => ['nullable', 'string', 'max:150'],
            'form.experience_years' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'form.current_company' => ['nullable', 'string', 'max:150'],
            'form.current_salary' => ['nullable', 'string', 'max:60'],
            'form.expected_salary' => ['nullable', 'string', 'max:60'],
            'form.notice_period' => ['nullable', 'string', 'max:60'],
            'form.portfolio_url' => ['nullable', 'url:http,https', 'max:500'],
            'form.linkedin_url' => ['nullable', 'url:http,https', 'max:500'],
            'form.cover_letter' => ['nullable', 'string', 'max:5000'],
            'resume' => ['required', ...JobApplication::RESUME_RULES],
            'consent' => ['accepted'],
        ], [
            'form.phone.regex' => 'Enter a phone number using digits only (a leading + is fine).',
            'resume.required' => 'Please attach your resume.',
            'resume.mimes' => 'The resume must be a PDF or Word file (.pdf, .doc, .docx).',
            'resume.max' => 'The resume must be 5 MB or smaller.',
            'consent.accepted' => 'Please tick the box to let us store your details for this application.',
            'form.portfolio_url.url' => 'Enter a full link starting with https://',
            'form.linkedin_url.url' => 'Enter a full link starting with https://',
        ], collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace(['_url', '_'], ['', ' '], $k)])->all());

        // Automated form-fillers complete the hidden field. Show them success and keep nothing.
        if ($this->website !== '') {
            $this->finish();

            return;
        }

        $key = 'job-application|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, self::HOURLY_LIMIT)) {
            $this->addError('form.name', 'Several applications were sent from this connection in a short time. Please try again in an hour.');

            return;
        }

        $email = strtolower(trim($this->form['email']));
        $already = JobApplication::where('email', $email)
            ->when($vacancy, fn ($q) => $q->where('job_vacancy_id', $vacancy->id), fn ($q) => $q->whereNull('job_vacancy_id'))
            ->where('created_at', '>=', now()->subDays(30))->exists();
        if ($already) {
            $this->addError('form.email', 'We already have a recent application from this email address for this position.');

            return;
        }

        RateLimiter::hit($key, 3600);

        $data = collect($this->form)->map(fn ($v) => trim((string) $v) === '' ? null : trim((string) $v))->all();
        $application = JobApplication::create(array_merge($data, [
            'email' => $email,
            'job_vacancy_id' => $vacancy?->id,
            'vacancy_title' => $vacancy?->title ?? self::GENERAL_TITLE,
            'resume_path' => $this->resume->store('resumes', 'local'),
            'resume_name' => str($this->resume->getClientOriginalName())->limit(180, '')->toString(),
            'ip_address' => request()->ip(),
        ]));

        Notification::send(self::team(), new JobApplicationReceived($application));
        Notification::route('mail', $application->email)->notify(new JobApplicationConfirmation($application));

        $this->finish();
    }

    protected function finish(): void
    {
        $this->reset('form', 'resume', 'consent', 'website');
        $this->submitted = true;
    }

    public function render()
    {
        $vacancy = $this->vacancyId ? JobVacancy::find($this->vacancyId) : null;
        abort_if($this->vacancyId && ! $vacancy, 404);

        return view('livewire.careers.apply', ['vacancy' => $vacancy, 'accepting' => $vacancy === null || $vacancy->isAccepting()])
            ->title($vacancy?->title ?? 'Send your resume');
    }
}
