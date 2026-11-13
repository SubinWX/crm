<?php

namespace App\Livewire\Team;

use App\Models\AttendanceRecord;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\User;
use App\Support\AttendanceSheet;
use App\Support\WorkCalendar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Team → Attendance. Everyone checks in and out and sees their own month. People with
 * "team.manage" see the whole team's monthly sheet, correct any day, and keep the holiday
 * list, the weekly off days and the yearly leave allowance.
 */
#[Title('Attendance')]
class Attendance extends Component
{
    #[Url(except: '')]
    public string $month = '';

    /** mine | sheet | setup */
    #[Url(except: 'mine')]
    public string $tab = 'mine';

    public ?int $cellUser = null;

    public string $cellDate = '';

    public array $cell = ['status' => '', 'note' => ''];

    public array $holiday = ['date' => '', 'name' => ''];

    public array $setup = ['weekly_off' => []];

    public function boot(): void
    {
        Gate::authorize('workspace');
    }

    public function mount(): void
    {
        if (! AttendanceSheet::validMonth($this->month)) {
            $this->month = today_local()->format('Y-m');
        }
        if (! $this->manager() || ! in_array($this->tab, ['mine', 'sheet', 'setup'], true)) {
            $this->tab = 'mine';
        }
        $this->setup = ['weekly_off' => array_map('strval', WorkCalendar::weeklyOff())];
    }

    protected function manager(): bool
    {
        return Gate::allows('team.manage');
    }

    public function updatedMonth(): void
    {
        if (! AttendanceSheet::validMonth($this->month)) {
            $this->month = today_local()->format('Y-m');
        }
    }

    public function shift(int $months): void
    {
        $this->month = Carbon::createFromFormat('Y-m-d', $this->month.'-01')->addMonthsNoOverflow($months)->format('Y-m');
    }

    // ------------------------------------------------------------- my day

    public function checkIn(): void
    {
        $today = today_local()->toDateString();
        $record = AttendanceRecord::firstOrNew(['user_id' => auth()->id(), 'date' => $today]);
        if ($record->exists && $record->check_in) {
            return;
        }
        $record->fill(['status' => $record->exists ? $record->status : 'present', 'check_in' => Carbon::now(crm_tz())->format('H:i:s')])->save();
        $this->dispatch('toast', message: 'Checked in at '.Carbon::now(crm_tz())->format('g:i A').'.');
    }

    public function checkOut(): void
    {
        $record = AttendanceRecord::where('user_id', auth()->id())->whereDate('date', today_local()->toDateString())->first();
        if (! $record || ! $record->check_in) {
            $this->dispatch('toast', message: 'Check in first.');

            return;
        }
        $record->update(['check_out' => Carbon::now(crm_tz())->format('H:i:s')]);
        $this->dispatch('toast', message: 'Checked out at '.Carbon::now(crm_tz())->format('g:i A').'.');
    }

    // -------------------------------------------------------- manager: sheet

    public function editCell(int $userId, string $date): void
    {
        abort_unless($this->manager() && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date <= today_local()->toDateString(), 403);
        $user = User::team()->findOrFail($userId);
        $record = AttendanceRecord::where('user_id', $user->id)->whereDate('date', $date)->first();
        $this->cellUser = $user->id;
        $this->cellDate = $date;
        $this->cell = ['status' => $record->status ?? '', 'note' => (string) ($record->note ?? '')];
        $this->resetValidation();
    }

    public function saveCell(): void
    {
        abort_unless($this->manager() && $this->cellUser, 403);
        $this->validate([
            'cell.status' => ['nullable', Rule::in(array_keys(AttendanceRecord::STATUSES))],
            'cell.note' => ['nullable', 'string', 'max:255'],
        ], [], ['cell.status' => 'status', 'cell.note' => 'note']);

        $existing = AttendanceRecord::where('user_id', $this->cellUser)->whereDate('date', $this->cellDate)->first();
        if ($this->cell['status'] === '') {
            // "Clear" removes the mark, so the day goes back to leave, holiday, weekly off or "not marked".
            $existing?->delete();
        } else {
            $data = ['status' => $this->cell['status'], 'note' => trim($this->cell['note']) ?: null, 'marked_by' => auth()->id()];
            $existing ? $existing->update($data) : AttendanceRecord::create($data + ['user_id' => $this->cellUser, 'date' => $this->cellDate]);
        }
        $this->cellUser = null;
        $this->dispatch('toast', message: 'Attendance updated.');
    }

    /** Marks everyone who has nothing recorded for that working day as present. People on leave are left alone. */
    public function markDayPresent(string $date): void
    {
        abort_unless($this->manager() && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date <= today_local()->toDateString(), 403);
        $sheet = new AttendanceSheet(substr($date, 0, 7), User::active()->team()->get());
        $count = 0;
        foreach ($sheet->cells as $userId => $days) {
            if (($days[$date]['code'] ?? '') === '?') {
                AttendanceRecord::create(['user_id' => $userId, 'date' => $date, 'status' => 'present', 'marked_by' => auth()->id()]);
                $count++;
            }
        }
        $this->dispatch('toast', message: $count ? "{$count} marked present for ".fmt_date($date).'.' : 'Nobody was unmarked on that day.');
    }

    // -------------------------------------------------------- manager: setup

    public function addHoliday(): void
    {
        abort_unless($this->manager(), 403);
        $this->validate([
            'holiday.date' => ['required', 'date', Rule::unique('holidays', 'date')],
            'holiday.name' => ['required', 'string', 'max:120'],
        ], ['holiday.date.unique' => 'There is already a holiday on that date.'], ['holiday.date' => 'date', 'holiday.name' => 'name']);
        Holiday::create(['date' => $this->holiday['date'], 'name' => trim($this->holiday['name'])]);
        $this->holiday = ['date' => '', 'name' => ''];
        $this->dispatch('toast', message: 'Holiday added.');
    }

    public function deleteHoliday(int $id): void
    {
        abort_unless($this->manager(), 403);
        Holiday::findOrFail($id)->delete();
    }

    public function saveSetup(): void
    {
        abort_unless($this->manager(), 403);
        $this->validate([
            'setup.weekly_off' => ['array', 'max:6'],
            'setup.weekly_off.*' => [Rule::in(['0', '1', '2', '3', '4', '5', '6'])],
        ], ['setup.weekly_off.max' => 'At least one day of the week has to be a working day.']);

        Setting::put('weekly_off_days', collect($this->setup['weekly_off'])->map(fn ($d) => (int) $d)->sort()->implode(','));
        activity('updated', 'Attendance and leave settings updated');
        $this->dispatch('toast', message: 'Saved.');
    }

    public function closeModal(): void
    {
        $this->cellUser = null;
    }

    public function render()
    {
        $manager = $this->manager();
        if (! $manager) {
            $this->tab = 'mine';
        }
        $me = auth()->user();
        $users = $this->tab === 'sheet' ? User::active()->team()->orderBy('name')->get() : collect([$me]);
        $sheet = new AttendanceSheet($this->month, $users);
        $today = today_local()->toDateString();

        return view('livewire.team.attendance', [
            'manager' => $manager,
            'sheet' => $sheet,
            'users' => $users,
            'me' => $me,
            'today' => $today,
            'todayRecord' => AttendanceRecord::where('user_id', $me->id)->whereDate('date', $today)->first(),
            'holidays' => $this->tab === 'setup' ? Holiday::where('date', '>=', today_local()->startOfYear()->toDateString())->orderBy('date')->get() : collect(),
            'cellPerson' => $this->cellUser ? User::find($this->cellUser) : null,
        ]);
    }
}
