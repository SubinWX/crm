<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Contracts\Queue\ShouldQueue;

/** To the people who approve leave: someone has asked for time off. */
class LeaveRequested extends CrmNotification implements ShouldQueue
{
    public function __construct(public LeaveRequest $leave) {}

    public function category(): string
    {
        return 'team';
    }

    public function title(): string
    {
        return 'Leave request from '.($this->leave->user?->name ?? 'a team member');
    }

    public function message(): string
    {
        return ($this->leave->user?->name ?? 'Someone').' asked for '.fmt_days($this->leave->days).' '.((float) $this->leave->days === 1.0 ? 'day' : 'days').' of '.strtolower($this->leave->type?->name ?? 'leave').': '.$this->leave->period().'.';
    }

    public function details(): array
    {
        return array_filter(['Dates' => $this->leave->period(), 'Type' => $this->leave->type?->name, 'Reason' => $this->leave->reason]);
    }

    public function url(): string
    {
        return route('leave', ['tab' => 'review']);
    }

    public function actionLabel(): string
    {
        return 'Review the request';
    }
}
