<?php

namespace App\Livewire\Calendar;

use App\Support\CalendarEvents;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Calendar')]
class Index extends Component
{
    /** Month being shown, as YYYY-MM. */
    #[Url(except: '')]
    public string $month = '';

    /** Selected day (YYYY-MM-DD) whose items are listed in full. */
    #[Url(except: '')]
    public string $day = '';

    /** Kinds of event that are switched off. */
    public array $hidden = [];

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month)) {
            $this->month = today_local()->format('Y-m');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->day)) {
            $this->day = str_starts_with(today_local()->toDateString(), $this->month) ? today_local()->toDateString() : '';
        }
    }

    protected function first(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $this->month.'-01', crm_tz())->startOfDay();
    }

    public function move(int $months): void
    {
        $this->month = $this->first()->addMonthsNoOverflow($months)->format('Y-m');
        $this->day = '';
    }

    public function goToday(): void
    {
        $this->month = today_local()->format('Y-m');
        $this->day = today_local()->toDateString();
    }

    public function selectDay(string $date): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->day = $this->day === $date ? '' : $date;
        }
    }

    public function toggleType(string $type): void
    {
        if (array_key_exists($type, CalendarEvents::TYPES)) {
            $this->hidden = in_array($type, $this->hidden, true) ? array_values(array_diff($this->hidden, [$type])) : [...$this->hidden, $type];
        }
    }

    public function render()
    {
        $first = $this->first();
        // Whole weeks, Monday to Sunday, covering the month.
        $gridStart = $first->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $first->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $allowed = CalendarEvents::allowedTypes(auth()->user());
        $shown = array_values(array_diff($allowed, $this->hidden));
        $events = CalendarEvents::between(auth()->user(), $gridStart, $gridEnd, $shown);

        $days = [];
        for ($date = $gridStart->copy(); $date->lte($gridEnd); $date->addDay()) {
            $days[] = $date->copy();
        }

        return view('livewire.calendar.index', [
            'first' => $first,
            'weeks' => array_chunk($days, 7),
            'byDay' => $events->groupBy('date'),
            'monthEvents' => $events->filter(fn ($e) => str_starts_with($e['date'], $this->month)),
            'allowed' => $allowed,
            'today' => today_local()->toDateString(),
            'canLeave' => Gate::allows('workspace'),
        ]);
    }
}
