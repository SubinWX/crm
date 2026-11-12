<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Contracts\Queue\ShouldQueue;

/** To the person who asked: their leave was approved or rejected. */
class LeaveDecided extends CrmNotification implements ShouldQueue
{
    public function __construct(public LeaveRequest $leave) {}

    public function category(): string
    {
        return 'team';
    }

    public function title(): string
    {
        return 'Your leave was '.($this->leave->status === 'approved' ? 'approved' : 'not approved');
    }

    public function message(): string
    {
        return ($this->leave->reviewer?->name ?? 'A manager').' '.($this->leave->status === 'approved' ? 'approved' : 'rejected').' your leave for '.$this->leave->period().'.';
    }

    public function details(): array
    {
        return array_filter(['Dates' => $this->leave->period(), 'Type' => $this->leave->type?->name, 'Note' => $this->leave->review_note]);
    }

    public function url(): string
    {
        return route('leave');
    }

    public function actionLabel(): string
    {
        return 'Open my leave';
    }
}
