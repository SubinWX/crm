<?php

namespace App\Livewire\Careers;

use App\Models\JobApplication;
use App\Models\JobVacancy;
use App\Models\Lookup;
use App\Support\ApplicationFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Team side of the careers section: job vacancies and the applications received for them. */
class Manage extends Component
{
    use WithPagination;

    public const CHART_RANGES = ['7' => '7 days', '30' => '30 days', '12m' => '12 months'];

    /** overview | applications | vacancies. Each is its own page in the Careers menu; set by the route. */
    #[Locked]
    public string $tab = 'overview';

    /** Period shown by the overview chart: 7 | 30 | 12m */
    #[Url(except: '30')]
    public string $chartRange = '30';

    #[Url(except: '')]
    public string $search = '';

    /** '' | vacancy id | general */
    #[Url(except: '')]
    public string $vacancy = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    /** Application to open straight away (used by notification links). */
    #[Url(except: '')]
    public string $open = '';

    public bool $showVacancyForm = false;

    public ?int $editingVacancyId = null;

    public array $form = [];

    /** Typed when "Add a new department" is chosen in the vacancy form. */
    public string $newDepartment = '';

    public ?int $viewingId = null;

    public array $review = [];

    public function boot(): void
    {
        Gate::authorize('careers.manage');
    }

    public function mount(string $tab = 'overview'): void
    {
        $this->tab = in_array($tab, ['overview', 'applications', 'vacancies'], true) ? $tab : 'overview';
        if (! array_key_exists($this->chartRange, self::CHART_RANGES)) {
            $this->chartRange = '30';
        }
        $this->resetForm();
        if (ctype_digit($this->open) && JobApplication::whereKey($this->open)->exists()) {
            $this->view((int) $this->open);
        }
        $this->open = '';
    }

    /**
     * Applications received per day (or per month), counted on the business calendar.
     *
     * @return array{bars: Collection, byVacancy: Collection, total: int}
     */
    protected function chart(): array
    {
        $monthly = $this->chartRange === '12m';
        $today = today_local();
        $start = $monthly ? $today->copy()->startOfMonth()->subMonthsNoOverflow(11) : $today->copy()->subDays((int) $this->chartRange - 1);

        $rows = JobApplication::query()->where('created_at', '>=', $start->copy()->utc())->get(['created_at', 'vacancy_title']);
        $counts = $rows->countBy(fn ($a) => $a->created_at->timezone(crm_tz())->format($monthly ? 'Y-m' : 'Y-m-d'));

        $bars = collect();
        for ($i = 0, $steps = $monthly ? 12 : (int) $this->chartRange; $i < $steps; $i++) {
            $point = $monthly ? $start->copy()->addMonthsNoOverflow($i) : $start->copy()->addDays($i);
            $bars->push([
                'count' => (int) ($counts[$point->format($monthly ? 'Y-m' : 'Y-m-d')] ?? 0),
                'title' => $point->format($monthly ? 'F Y' : 'D, d M Y'),
                // Thirty daily labels do not fit, so only every fifth day is named.
                'label' => $monthly ? $point->format('M') : ($steps <= 7 ? $point->format('D d') : ($i % 5 === 0 || $i === $steps - 1 ? $point->format('d M') : '')),
            ]);
        }

        return ['bars' => $bars, 'byVacancy' => $rows->countBy('vacancy_title')->sortKeys()->sortDesc()->take(8), 'total' => $rows->count()];
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'vacancy', 'status', 'from', 'to', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function filters(): array
    {
        return ['search' => $this->search, 'vacancy' => $this->vacancy, 'status' => $this->status, 'from' => $this->from, 'to' => $this->to];
    }

    public function setRange(string $range): void
    {
        $today = today_local();
        [$this->from, $this->to] = match ($range) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'week' => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
            'month' => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
            default => ['', ''],
        };
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'vacancy', 'status', 'from', 'to', 'sort');
        $this->resetPage();
    }

    // ---------------------------------------------------------------- vacancies

    protected function resetForm(): void
    {
        $this->form = [
            'title' => '', 'department' => '', 'location' => '', 'employment_type' => 'full_time', 'work_mode' => 'onsite',
            'experience' => '', 'salary_range' => '', 'openings' => '1', 'summary' => '', 'description' => '', 'requirements' => '',
            'status' => 'open', 'closes_on' => '',
        ];
        $this->editingVacancyId = null;
        $this->newDepartment = '';
    }

    public function createVacancy(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->showVacancyForm = true;
    }

    public function editVacancy(int $id): void
    {
        $vacancy = JobVacancy::findOrFail($id);
        $this->resetForm();
        $this->resetValidation();
        $this->editingVacancyId = $vacancy->id;
        foreach (array_keys($this->form) as $key) {
            $value = $vacancy->{$key};
            $this->form[$key] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) ($value ?? '');
        }
        $this->showVacancyForm = true;
    }

    public function saveVacancy(): void
    {
        $this->validate([
            'form.title' => ['required', 'string', 'min:3', 'max:150'],
            'form.department' => ['nullable', 'string', 'max:100'],
            'newDepartment' => [Rule::requiredIf($this->form['department'] === '__new'), 'nullable', 'string', 'min:2', 'max:100'],
            'form.location' => ['nullable', 'string', 'max:150'],
            'form.employment_type' => ['required', Rule::in(array_keys(JobVacancy::EMPLOYMENT_TYPES))],
            'form.work_mode' => ['required', Rule::in(array_keys(JobVacancy::WORK_MODES))],
            'form.experience' => ['nullable', 'string', 'max:100'],
            'form.salary_range' => ['nullable', 'string', 'max:100'],
            'form.openings' => ['required', 'integer', 'min:1', 'max:999'],
            'form.summary' => ['nullable', 'string', 'max:500'],
            'form.description' => ['required', 'string', 'min:20', 'max:20000'],
            'form.requirements' => ['nullable', 'string', 'max:20000'],
            'form.status' => ['required', Rule::in(array_keys(JobVacancy::STATUSES))],
            'form.closes_on' => ['nullable', 'date'],
        ], [], collect($this->form)->keys()->mapWithKeys(fn ($k) => ["form.{$k}" => str_replace('_', ' ', $k)])->all() + ['newDepartment' => 'new department name']);

        $data = collect($this->form)->map(fn ($v) => is_string($v) && trim($v) === '' ? null : (is_string($v) ? trim($v) : $v))->all();

        if ($data['department'] === '__new') {
            // Reuse an existing entry that differs only by capitals, and switch it back on if it was hidden.
            $name = trim($this->newDepartment);
            $department = Lookup::where('type', 'job_department')->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first()
                ?? Lookup::create(['type' => 'job_department', 'name' => $name, 'sort_order' => (int) Lookup::where('type', 'job_department')->max('sort_order') + 1]);
            $department->is_active || $department->update(['is_active' => true]);
            $data['department'] = $department->name;
        }

        if ($this->editingVacancyId) {
            // The public address stays the same once published, so shared links keep working.
            JobVacancy::findOrFail($this->editingVacancyId)->update($data);
        } else {
            JobVacancy::create($data + ['slug' => JobVacancy::uniqueSlug($data['title']), 'created_by' => auth()->id()]);
        }

        $this->showVacancyForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Vacancy saved.');
    }

    public function setVacancyStatus(int $id, string $status): void
    {
        abort_unless(array_key_exists($status, JobVacancy::STATUSES), 422);
        JobVacancy::findOrFail($id)->update(['status' => $status]);
        $this->dispatch('toast', message: 'Vacancy marked '.strtolower(JobVacancy::STATUSES[$status]).'.');
    }

    /** Applications already received are kept; they simply lose the link to the vacancy. */
    public function deleteVacancy(int $id): void
    {
        JobVacancy::findOrFail($id)->delete();
        $this->dispatch('toast', message: 'Vacancy deleted.');
    }

    // -------------------------------------------------------------- applications

    public function view(int $id): void
    {
        $application = JobApplication::findOrFail($id);
        if ($application->status === 'new') {
            $application->update(['status' => 'reviewing', 'reviewed_by' => auth()->id()]);
        }
        $this->resetValidation();
        $this->viewingId = $application->id;
        $this->review = [
            'status' => $application->status,
            'rating' => (string) ($application->rating ?? ''),
            'interview_at' => $application->interview_at?->timezone(crm_tz())->format('Y-m-d\TH:i') ?? '',
            'notes' => (string) $application->notes,
        ];
    }

    public function saveReview(): void
    {
        $application = JobApplication::findOrFail($this->viewingId);

        $this->validate([
            'review.status' => ['required', Rule::in(array_keys(JobApplication::STATUSES))],
            'review.rating' => ['nullable', 'integer', 'between:1,5'],
            'review.interview_at' => ['nullable', 'date'],
            'review.notes' => ['nullable', 'string', 'max:5000'],
        ], [], ['review.status' => 'status', 'review.rating' => 'rating', 'review.interview_at' => 'interview date', 'review.notes' => 'notes']);

        $application->update([
            'status' => $this->review['status'],
            'rating' => $this->review['rating'] === '' ? null : (int) $this->review['rating'],
            // Typed in the business time zone, stored in UTC like every other timestamp.
            'interview_at' => $this->review['interview_at'] === '' ? null : Carbon::parse($this->review['interview_at'], crm_tz())->utc(),
            'notes' => trim($this->review['notes']) ?: null,
            'reviewed_by' => auth()->id(),
        ]);

        $this->viewingId = null;
        $this->dispatch('toast', message: 'Application updated.');
    }

    public function setStatus(int $id, string $status): void
    {
        abort_unless(array_key_exists($status, JobApplication::STATUSES), 422);
        JobApplication::findOrFail($id)->update(['status' => $status, 'reviewed_by' => auth()->id()]);
    }

    public function deleteApplication(int $id): void
    {
        $application = JobApplication::findOrFail($id);
        Storage::disk('local')->delete($application->resume_path);
        $application->delete();
        $this->viewingId = null;
        $this->dispatch('toast', message: 'Application and resume deleted.');
    }

    public function closeModal(): void
    {
        $this->showVacancyForm = false;
        $this->viewingId = null;
    }

    public function render()
    {
        $filtered = fn () => ApplicationFilters::apply(JobApplication::query(), $this->filters());
        $statusCounts = ApplicationFilters::apply(JobApplication::query(), ['status' => null] + $this->filters())
            ->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('livewire.careers.manage', [
            'applications' => $this->tab === 'applications'
                ? ApplicationFilters::sort($filtered()->with('vacancy'), $this->sort)->paginate(25)
                : null,
            'statusCounts' => $statusCounts,
            'vacancies' => JobVacancy::query()->withCount(['applications', 'applications as new_count' => fn ($q) => $q->where('status', 'new')])
                ->orderByRaw("field(status, 'open', 'draft', 'closed')")->latest()->get(),
            // The list from Settings, plus the department of the vacancy being edited if it has since been removed.
            'departments' => Lookup::where('type', 'job_department')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name')
                ->when(! in_array($this->form['department'] ?? '', ['', '__new'], true), fn ($list) => $list->push($this->form['department']))->unique()->values(),
            'viewing' => $this->viewingId ? JobApplication::with(['vacancy', 'reviewer'])->find($this->viewingId) : null,
            'totals' => [
                'open' => JobVacancy::accepting()->count(),
                'new' => JobApplication::where('status', 'new')->count(),
                'month' => JobApplication::where('created_at', '>=', today_local()->startOfMonth()->utc())->count(),
                'interviews' => JobApplication::where('interview_at', '>=', now())->whereNotIn('status', ['rejected', 'hired'])->count(),
            ],
            'hasGeneral' => JobApplication::whereNull('job_vacancy_id')->exists(),
            // Overview: only what arrived today and in the two days before it.
            'recent' => $this->tab === 'overview'
                ? JobApplication::query()->with('vacancy')->where('created_at', '>=', today_local()->subDays(2)->utc())->latest()->latest('id')->limit(100)->get()
                : collect(),
            'chart' => $this->tab === 'overview' ? $this->chart() : null,
        ])->title(['overview' => 'Careers', 'applications' => 'Job Applications', 'vacancies' => 'Job Vacancies'][$this->tab]);
    }
}
