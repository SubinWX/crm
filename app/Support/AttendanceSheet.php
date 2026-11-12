<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One month of attendance for a set of people, as a grid of day codes:
 *
 *   P present · HD half day · WFH work from home · A absent   (marked by check-in or by a manager)
 *   L on approved leave · HL half-day leave                    (from approved leave requests)
 *   H company holiday · W weekly off                           (from the calendar)
 *   ?  a past working day nobody has marked yet · blank = a day still to come
 *
 * A manual mark always wins over leave, a holiday or a weekly off.
 */
class AttendanceSheet
{
    public const CODES = [
        'P' => ['Present', 'bg-emerald-100 text-emerald-800'], 'HD' => ['Half day', 'bg-amber-100 text-amber-800'],
        'WFH' => ['Work from home', 'bg-sky-100 text-sky-800'], 'A' => ['Absent', 'bg-red-100 text-red-800'],
        'L' => ['On leave', 'bg-violet-100 text-violet-800'], 'HL' => ['Half-day leave', 'bg-violet-50 text-violet-700'],
        'H' => ['Holiday', 'bg-stone-200 text-stone-700'], 'W' => ['Weekly off', 'bg-stone-100 text-stone-400'],
        '?' => ['Not marked', 'bg-white text-red-600'],
    ];

    public const STATUS_CODES = ['present' => 'P', 'half_day' => 'HD', 'wfh' => 'WFH', 'absent' => 'A'];

    public Carbon $start;

    public Carbon $end;

    /** @var Collection<int, Carbon> */
    public Collection $days;

    /** @var array<string, string> date => holiday name */
    public array $holidays;

    /** @var array<int, array<string, array{code:string, record:?AttendanceRecord, note:?string}>> user id => date => cell */
    public array $cells = [];

    /** @var array<int, array<string, float|int>> user id => totals */
    public array $totals = [];

    public static function validMonth(?string $month): bool
    {
        return (bool) preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', (string) $month);
    }

    /** @param Collection<int, User> $users */
    public function __construct(string $month, Collection $users)
    {
        $this->start = Carbon::createFromFormat('Y-m-d', $month.'-01', crm_tz())->startOfDay();
        $this->end = $this->start->copy()->endOfMonth()->startOfDay();
        $this->days = collect(range(0, $this->start->daysInMonth - 1))->map(fn ($i) => $this->start->copy()->addDays($i));
        [$from, $to] = [$this->start->toDateString(), $this->end->toDateString()];
        $today = today_local()->toDateString();
        $this->holidays = WorkCalendar::holidays($from, $to);

        $records = AttendanceRecord::whereIn('user_id', $users->pluck('id'))->whereBetween('date', [$from, $to])->get()
            ->groupBy('user_id')->map(fn ($rows) => $rows->keyBy(fn ($r) => $r->date->toDateString()));
        $leaves = LeaveRequest::with('type')->whereIn('user_id', $users->pluck('id'))->where('status', 'approved')->overlapping($from, $to)->get()->groupBy('user_id');

        foreach ($users as $user) {
            $totals = ['present' => 0.0, 'absent' => 0.0, 'leave' => 0.0, 'unmarked' => 0, 'working' => 0];
            foreach ($this->days as $day) {
                $date = $day->toDateString();
                $record = $records[$user->id][$date] ?? null;
                $leave = ($leaves[$user->id] ?? collect())->first(fn ($l) => $l->from_date->toDateString() <= $date && $l->to_date->toDateString() >= $date);
                $off = isset($this->holidays[$date]) || WorkCalendar::isWeeklyOff($day);
                if (! $off) {
                    $totals['working']++;
                }

                $code = match (true) {
                    $record !== null => self::STATUS_CODES[$record->status] ?? 'P',
                    isset($this->holidays[$date]) => 'H',
                    WorkCalendar::isWeeklyOff($day) => 'W',
                    $leave !== null => $leave->day_part === 'full' ? 'L' : 'HL',
                    $date <= $today => '?',
                    default => '',
                };
                match ($code) {
                    'P', 'WFH' => $totals['present'] += 1,
                    'HD' => [$totals['present'] += 0.5, $totals['absent'] += $leave ? 0 : 0.5, $totals['leave'] += $leave ? 0.5 : 0],
                    'A' => $totals['absent'] += 1,
                    'L' => $totals['leave'] += 1,
                    'HL' => [$totals['leave'] += 0.5, $totals['present'] += 0.5],
                    '?' => $totals['unmarked']++,
                    default => null,
                };
                $this->cells[$user->id][$date] = [
                    'code' => $code, 'record' => $record,
                    'note' => $record?->note ?: ($code === 'H' ? $this->holidays[$date] : ($leave && in_array($code, ['L', 'HL'], true) ? ($leave->type?->name ?? 'Leave') : null)),
                ];
            }
            $this->totals[$user->id] = $totals;
        }
    }
}
