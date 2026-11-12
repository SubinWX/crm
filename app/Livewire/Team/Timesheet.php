<?php

namespace App\Livewire\Team;

use App\Models\Client;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\TimeFilters;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Team → Timesheet. Everyone logs the hours they spent on projects, one week at a time.
 * People with "team.manage" can also look at anyone's week, or everyone's together.
 */
#[Title('Timesheet')]
class Timesheet extends Component
{
    use WithPagination;

    /** Colours of the Summary ring on the Overview tab, one per project. */
    public const RING_COLORS = ['#1e3a5f', '#4a6fa5', '#8fb0d6', '#b9cde6', '#2f6f5e', '#6aa893', '#c9a227', '#b5651d', '#8a8f98'];

    // Overview tab filters
    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(except: '')]
    public string $task = '';

    #[Url(except: '')]
    public string $client = '';

    public int $perPage = 10;

    /** Any date inside the week being shown. */
    #[Url(except: '')]
    public string $week = '';

    /** '' = me, 'all' = everyone, or a user id. Only managers can change it. */
    #[Url(except: '')]
    public string $person = '';

    #[Url(except: '')]
    public string $project = '';

    /** tracker (timer and the day-by-day list) | overview (totals and charts) */
    #[Url(except: 'tracker')]
    public string $tab = 'tracker';

    /** timer | manual: how the bar at the top of the tracker adds time */
    #[Url(except: 'timer')]
    public string $mode = 'timer';

    /** What the timer bar holds before Start is pressed. */
    public array $timer = ['description' => '', 'project_id' => '', 'is_billable' => true];

    /** The manual bar: one line to add finished work without opening the full form. */
    public array $quick = ['description' => '', 'project_id' => '', 'work_date' => '', 'hours' => '', 'is_billable' => true];

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public function boot(): void
    {
        Gate::authorize('workspace');
    }

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->week)) {
            $this->week = today_local()->toDateString();
        }
        // Overview opens on this month so far.
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->from)) {
            $this->from = today_local()->startOfMonth()->toDateString();
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->to)) {
            $this->to = today_local()->toDateString();
        }
        if (! in_array($this->tab, ['tracker', 'overview'], true)) {
            $this->tab = 'tracker';
        }
        if (! in_array($this->mode, ['timer', 'manual'], true)) {
            $this->mode = 'timer';
        }
        $this->resetForm();
        $this->quick['work_date'] = today_local()->toDateString();
        if (ctype_digit($this->project)) {
            $this->timer['project_id'] = $this->quick['project_id'] = $this->project;
        }
        if (request()->boolean('add')) {
            $this->showForm = true;
        }
    }

    public function updated($property): void
    {
        if (in_array($property, ['from', 'to', 'task', 'client', 'project', 'person', 'perPage', 'tab'], true)) {
            $this->resetPage();
        }
        if (! in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 10;
        }
    }

    public function setRange(string $range): void
    {
        $today = today_local();
        [$this->from, $this->to] = match ($range) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'week' => [$today->copy()->startOfWeek(Carbon::MONDAY)->toDateString(), $today->toDateString()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            default => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
        };
        $this->resetPage();
    }

    /** The filters of the Overview tab, also used by its export so both always agree. */
    public function overviewFilters(): array
    {
        return array_filter(['from' => $this->from, 'to' => $this->to, 'task' => $this->task, 'client' => $this->client, 'project' => $this->project, 'person' => $this->person]);
    }

    /**
     * Everything the Overview tab shows for the chosen dates and filters: totals, hours per day,
     * the split by project and the list of entries.
     */
    protected function overview(?int $userId): array
    {
        // A date range the wrong way round, or longer than a year, is tidied up rather than refused.
        [$from, $to] = $this->from <= $this->to ? [$this->from, $this->to] : [$this->to, $this->from];
        if (Carbon::parse($from)->diffInDays(Carbon::parse($to)) > 366) {
            $from = Carbon::parse($to)->subDays(366)->toDateString();
        }

        $query = fn () => TimeFilters::apply(TimeEntry::query()->finished(), ['from' => $from, 'to' => $to, 'task' => $this->task, 'client' => $this->client, 'project' => $this->project], $userId);
        $all = $query()->with('project:id,name')->get(['id', 'project_id', 'work_date', 'minutes', 'is_billable', 'started_at', 'ended_at']);
        $total = $all->sum(fn ($e) => $e->seconds());
        $billable = $all->where('is_billable', true)->sum(fn ($e) => $e->seconds());

        // One bar per day. Long ranges are grouped by week so the chart stays readable.
        $perDay = $all->groupBy(fn ($e) => $e->work_date->toDateString())->map(fn ($rows) => $rows->sum(fn ($e) => $e->seconds()));
        $span = (int) Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1;
        $bars = collect();
        if ($span <= 45) {
            for ($day = Carbon::parse($from); $day->toDateString() <= $to; $day->addDay()) {
                $bars->push(['label' => $day->format('D, M j'), 'seconds' => (int) ($perDay[$day->toDateString()] ?? 0)]);
            }
        } else {
            for ($week = Carbon::parse($from)->startOfWeek(Carbon::MONDAY); $week->toDateString() <= $to; $week->addWeek()) {
                $end = $week->copy()->addDays(6)->toDateString();
                $bars->push(['label' => 'Week of '.$week->format('M j'), 'seconds' => (int) $perDay->filter(fn ($s, $d) => $d >= $week->toDateString() && $d <= $end)->sum()]);
            }
        }

        // Split by project for the ring: the largest eight, the rest together.
        $byProject = $all->groupBy('project_id')->map(fn ($rows) => ['name' => $rows->first()->project?->name ?? 'Unknown', 'seconds' => $rows->sum(fn ($e) => $e->seconds())])->sortByDesc('seconds')->values();
        if ($byProject->count() > 8) {
            $byProject = $byProject->take(8)->push(['name' => 'Other projects', 'seconds' => $byProject->skip(8)->sum('seconds')]);
        }
        $offset = 0;
        $slices = $byProject->values()->map(function ($slice, $index) use ($total, &$offset) {
            $percent = $total > 0 ? round($slice['seconds'] / $total * 100, 2) : 0;
            $slice += ['percent' => $percent, 'offset' => $offset, 'color' => self::RING_COLORS[$index % count(self::RING_COLORS)]];
            $offset += $percent;

            return $slice;
        });

        return [
            'from' => $from, 'to' => $to, 'total' => $total, 'billable' => $billable, 'nonBillable' => $total - $billable,
            'bars' => $bars, 'peak' => max(3600, (int) $bars->max('seconds')), 'slices' => $slices,
            'rows' => $query()->with(['project.client', 'user'])->orderByDesc('work_date')->orderByDesc('started_at')->orderByDesc('id')->paginate($this->perPage),
            'clients' => Client::whereIn('id', Project::whereIn('id', TimeEntry::query()->when($userId, fn ($q) => $q->where('user_id', $userId))->select('project_id'))->select('client_id'))->orderBy('name')->get(['id', 'name']),
        ];
    }

    // ------------------------------------------------------------------ timer

    protected function running(): ?TimeEntry
    {
        return TimeEntry::running()->where('user_id', auth()->id())->latest('started_at')->first();
    }

    /** Starts the clock on a project. A timer that is already running is stopped and saved first. */
    public function startTimer(): void
    {
        $this->validate([
            'timer.project_id' => ['required', function ($attribute, $value, $fail) {
                if (! Project::loggableBy(auth()->user())->whereKey($value)->exists()) {
                    $fail('Choose one of your open projects.');
                }
            }],
            'timer.description' => ['nullable', 'string', 'max:500'],
        ], ['timer.project_id.required' => 'Choose the project you are working on.'], ['timer.project_id' => 'project', 'timer.description' => 'description']);

        $this->running()?->stop();
        TimeEntry::create([
            'user_id' => auth()->id(), 'project_id' => (int) $this->timer['project_id'], 'work_date' => today_local()->toDateString(), 'minutes' => 0,
            'started_at' => now(), 'description' => trim((string) $this->timer['description']) ?: null, 'is_billable' => (bool) $this->timer['is_billable'],
        ]);
        $this->week = today_local()->toDateString();
        $this->timer['description'] = '';
    }

    public function stopTimer(): void
    {
        if ($entry = $this->running()) {
            $entry->stop();
            $this->dispatch('toast', message: 'Stopped. '.TimeEntry::clock($entry->seconds()).' saved to '.($entry->project?->name ?? 'the project').'.');
        }
    }

    /** Throws the running timer away without saving it. */
    public function discardTimer(): void
    {
        $this->running()?->delete();
    }

    /** The play button on a row: start a new timer with the same project and description. */
    public function restart(int $id): void
    {
        $entry = TimeEntry::where('user_id', auth()->id())->find($id) ?? abort(403);
        $this->timer = ['description' => (string) $entry->description, 'project_id' => (string) $entry->project_id, 'is_billable' => $entry->is_billable];
        $this->mode = 'timer';
        $this->startTimer();
    }

    /** The manual bar: add finished work in one line. */
    public function quickAdd(): void
    {
        $this->form = [
            'work_date' => $this->quick['work_date'], 'project_id' => $this->quick['project_id'], 'hours' => $this->quick['hours'],
            'description' => $this->quick['description'], 'is_billable' => $this->quick['is_billable'],
        ];
        $this->editingId = null;
        $this->save();
        // Errors raised on the full form's fields are shown under the matching quick field.
        foreach ($this->getErrorBag()->getMessages() as $key => $messages) {
            if (str_starts_with($key, 'form.')) {
                $this->addError('quick.'.substr($key, 5), $messages[0]);
            }
        }
        if ($this->getErrorBag()->isEmpty()) {
            $this->quick = ['description' => '', 'project_id' => $this->quick['project_id'], 'work_date' => $this->quick['work_date'], 'hours' => '', 'is_billable' => true];
        }
    }

    protected function manager(): bool
    {
        return Gate::allows('team.manage');
    }

    /** Monday and Sunday of the week being shown. */
    protected function range(): array
    {
        $start = Carbon::parse($this->week)->startOfWeek(Carbon::MONDAY);

        return [$start, $start->copy()->addDays(6)];
    }

    public function shift(int $weeks): void
    {
        $this->week = Carbon::parse($this->week)->addWeeks($weeks)->toDateString();
    }

    public function thisWeek(): void
    {
        $this->week = today_local()->toDateString();
    }

    protected function resetForm(): void
    {
        $today = today_local()->toDateString();
        [$start, $end] = $this->range();
        $this->form = [
            // Default to today when it is inside the week on screen, otherwise that week's Monday.
            'work_date' => $today >= $start->toDateString() && $today <= $end->toDateString() ? $today : $start->toDateString(),
            'project_id' => ctype_digit($this->project) ? $this->project : '', 'hours' => '', 'description' => '', 'is_billable' => true,
        ];
        $this->editingId = null;
    }

    public function create(): void
    {
        $this->resetForm();
        $this->resetValidation();
        $this->showForm = true;
    }

    /** Your own entry inside the editing window, or any entry for a manager. */
    protected function editable(int $id): TimeEntry
    {
        $entry = TimeEntry::findOrFail($id);
        if ($this->manager()) {
            return $entry;
        }
        abort_unless($entry->user_id === auth()->id() && $entry->work_date->toDateString() >= today_local()->subDays(TimeEntry::EDIT_DAYS)->toDateString(), 403);

        return $entry;
    }

    public function edit(int $id): void
    {
        $entry = $this->editable($id);
        abort_if($entry->isRunning(), 422, 'Stop the timer before editing it.');
        $this->resetValidation();
        $this->editingId = $entry->id;
        $this->form = [
            'work_date' => $entry->work_date->toDateString(), 'project_id' => (string) $entry->project_id,
            'hours' => (string) round($entry->minutes / 60, 2), 'description' => (string) $entry->description, 'is_billable' => $entry->is_billable,
        ];
        $this->showForm = true;
    }

    public function save(): void
    {
        $entry = $this->editingId ? $this->editable($this->editingId) : null;
        $owner = $entry ? $entry->user : auth()->user();
        $earliest = today_local()->subDays(TimeEntry::EDIT_DAYS)->toDateString();

        try {
            $this->validateEntry($earliest, $owner, $entry);
        } catch (ValidationException $e) {
            // The one-line manual bar reports these under its own fields.
            if ($this->showForm) {
                throw $e;
            }
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->finishSave($entry, $owner);
    }

    protected function validateEntry(string $earliest, User $owner, ?TimeEntry $entry): void
    {
        $this->validate([
            'form.work_date' => ['required', 'date', 'before_or_equal:'.today_local()->toDateString(), ...($this->manager() ? [] : ['after_or_equal:'.$earliest])],
            // A project the entry's owner may log to, or the one this entry already belongs to.
            'form.project_id' => ['required', function ($attribute, $value, $fail) use ($owner, $entry) {
                $allowed = Project::loggableBy($owner)->whereKey($value)->exists() || ($entry && (string) $entry->project_id === (string) $value);
                if (! $allowed) {
                    $fail('Choose one of your open projects.');
                }
            }],
            'form.hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'form.description' => ['nullable', 'string', 'max:500'],
            'form.is_billable' => ['boolean'],
        ], [
            'form.work_date.before_or_equal' => 'Time cannot be logged for a future date.',
            'form.work_date.after_or_equal' => 'You can log or change time for the last '.TimeEntry::EDIT_DAYS.' days. Ask a manager for anything older.',
            'form.hours.min' => 'Enter at least 0.25 hours (15 minutes).',
        ], ['form.work_date' => 'date', 'form.project_id' => 'project', 'form.hours' => 'hours', 'form.description' => 'description']);
    }

    protected function finishSave(?TimeEntry $entry, User $owner): void
    {
        $minutes = (int) round((float) $this->form['hours'] * 60);
        $dayTotal = (int) TimeEntry::where('user_id', $owner->id)->whereDate('work_date', $this->form['work_date'])
            ->when($entry, fn ($q) => $q->whereKeyNot($entry->id))->sum('minutes');
        if ($dayTotal + $minutes > 24 * 60) {
            $this->addError('form.hours', 'That would make more than 24 hours on this day ('.TimeEntry::format($dayTotal).' already logged).');

            return;
        }

        $data = [
            'work_date' => $this->form['work_date'], 'project_id' => (int) $this->form['project_id'], 'minutes' => $minutes,
            'description' => trim((string) $this->form['description']) ?: null, 'is_billable' => (bool) $this->form['is_billable'],
        ];
        // Once the hours or the date of a timed entry are typed over, its start and end times no longer describe it.
        if ($entry && $entry->started_at && ($entry->minutes !== $minutes || $entry->work_date->toDateString() !== $data['work_date'])) {
            $data += ['started_at' => null, 'ended_at' => null];
        }
        $entry ? $entry->update($data) : TimeEntry::create($data + ['user_id' => auth()->id()]);

        // Jump to the week the entry is in, so it is visible straight away.
        $this->week = $this->form['work_date'];
        $this->showForm = false;
        $this->resetForm();
        $this->dispatch('toast', message: 'Time saved.');
    }

    public function delete(int $id): void
    {
        $this->editable($id)->delete();
        $this->dispatch('toast', message: 'Entry deleted.');
    }

    public function closeModal(): void
    {
        $this->showForm = false;
    }

    public function render()
    {
        $manager = $this->manager();
        if (! $manager) {
            $this->person = '';
        }
        [$start, $end] = $this->range();
        $userId = $this->person === 'all' ? null : (ctype_digit($this->person) ? (int) $this->person : auth()->id());

        $query = fn () => TimeEntry::query()->finished()
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(ctype_digit($this->project), fn ($q) => $q->where('project_id', (int) $this->project));

        $byDay = $query()->toBase()->selectRaw('work_date, sum(minutes) as minutes')->groupBy('work_date')->pluck('minutes', 'work_date')
            ->mapWithKeys(fn ($m, $d) => [substr((string) $d, 0, 10) => (int) $m]);
        $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i))->map(fn (Carbon $d) => [
            'date' => $d->toDateString(), 'label' => $d->format('D'), 'day' => $d->format('d M'), 'minutes' => $byDay[$d->toDateString()] ?? 0,
            'today' => $d->toDateString() === today_local()->toDateString(), 'future' => $d->toDateString() > today_local()->toDateString(),
        ]);
        $editing = $this->editingId ? TimeEntry::find($this->editingId) : null;
        $formOwner = $editing?->user ?? auth()->user();

        $entries = $query()->with(['project.client', 'user'])->orderByDesc('work_date')->orderByDesc('started_at')->orderByDesc('id')->get();
        $today = today_local()->toDateString();
        $running = $this->running();

        return view('livewire.team.timesheet', [
            'overview' => $this->tab === 'overview' ? $this->overview($userId) : null,
            'running' => $running?->load('project'),
            // The tracker list: one block per day, newest first, each with its own total.
            'groups' => $entries->groupBy(fn ($e) => $e->work_date->toDateString())->map(fn ($rows, $date) => [
                'label' => $date === $today ? 'Today' : ($date === today_local()->subDay()->toDateString() ? 'Yesterday' : Carbon::parse($date)->format('l, d M')),
                'seconds' => $rows->sum(fn ($e) => $e->seconds()), 'rows' => $rows,
            ]),
            'weekSeconds' => $entries->sum(fn ($e) => $e->seconds()),
            'manager' => $manager,
            'start' => $start, 'end' => $end, 'days' => $days,
            'entries' => $entries,
            'total' => (int) $query()->sum('minutes'),
            'billable' => (int) $query()->where('is_billable', true)->sum('minutes'),
            'byProject' => $query()->toBase()->join('projects', 'projects.id', '=', 'time_entries.project_id')
                ->selectRaw('projects.name as name, sum(time_entries.minutes) as minutes')->groupBy('projects.id', 'projects.name')->orderByDesc('minutes')->get(),
            'people' => $manager ? User::active()->team()->orderBy('name')->get(['id', 'name']) : collect(),
            'projects' => Project::query()->with('client')->where(fn ($q) => $q->loggableBy($formOwner)->when($editing, fn ($q) => $q->orWhere('projects.id', $editing->project_id)))->orderBy('name')->get(),
            'filterProjects' => Project::query()->whereIn('id', TimeEntry::query()->when($userId, fn ($q) => $q->where('user_id', $userId))->select('project_id'))->orderBy('name')->get(['id', 'name']),
            'editLimit' => today_local()->subDays(TimeEntry::EDIT_DAYS)->toDateString(),
            'viewing' => $this->person === 'all' ? 'Everyone' : ($userId === auth()->id() ? 'My time' : (User::find($userId)?->name ?? 'Unknown')),
        ]);
    }
}
