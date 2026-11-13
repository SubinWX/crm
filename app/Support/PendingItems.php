<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use App\Models\ChatMessage;
use App\Models\HostingRecord;
use App\Models\Invoice;
use App\Models\JobApplication;
use App\Models\Lead;
use App\Models\LeaveRequest;
use App\Models\Renewal;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\User;

/**
 * "Needs your attention" on the dashboard: everything waiting for one person, counted from the
 * areas they can open. Shown to everyone right after sign-in; items with nothing waiting are left out.
 */
class PendingItems
{
    /**
     * @return array<int, array{label: string, count: int, url: string, urgent: bool, icon: string}>
     */
    public static function for(User $user): array
    {
        $today = today_local()->toDateString();
        $soon = today_local()->addDays(7)->toDateString();
        $items = [];
        $add = function (int $count, string $one, string $many, string $url, string $icon, bool $urgent = false) use (&$items) {
            if ($count > 0) {
                $items[] = ['label' => $count === 1 ? $one : $many, 'count' => $count, 'url' => $url, 'urgent' => $urgent, 'icon' => $icon];
            }
        };
        $workspace = $user->can('workspace');

        $add($user->unreadNotifications()->count(), 'unread notification', 'unread notifications', route('notifications'), 'bell');
        $add(ChatMessage::unreadFor($user), 'unread chat message', 'unread chat messages', route('chat'), 'chat');

        if ($workspace) {
            // Not checked in on a working day.
            if (! WorkCalendar::isWeeklyOff(today_local()) && WorkCalendar::holidays($today, $today) === []
                && ! AttendanceRecord::where('user_id', $user->id)->whereDate('date', $today)->exists()
                && ! LeaveRequest::where('user_id', $user->id)->where('status', 'approved')->where('from_date', '<=', $today)->where('to_date', '>=', $today)->exists()) {
                $items[] = ['label' => 'You have not checked in today', 'count' => 0, 'url' => route('attendance'), 'urgent' => false, 'icon' => 'check'];
            }
            $add(LeaveRequest::where('user_id', $user->id)->where('status', 'pending')->count(), 'leave request of yours waiting for approval', 'leave requests of yours waiting for approval', route('leave'), 'calendar');
        }
        if ($user->can('team.manage')) {
            $add(LeaveRequest::where('status', 'pending')->where('user_id', '!=', $user->id)->count(), 'leave request to approve', 'leave requests to approve', route('leave', ['tab' => 'review']), 'calendar', true);
        }

        if ($user->can('tasks.view')) {
            $mine = fn () => Task::query()->open()->assignedTo($user->id);
            $overdue = $mine()->whereNotNull('due_date')->where('due_date', '<', $today)->count();
            $add($overdue, 'task of yours is overdue', 'tasks of yours are overdue', route('tasks.index', ['assignee' => $user->id]), 'check', true);
            $add($mine()->count() - $overdue, 'open task assigned to you', 'open tasks assigned to you', route('tasks.index', ['assignee' => $user->id]), 'check');
        }

        if ($user->can('support.manage')) {
            $add(SupportTicket::where('status', 'open')->count(), 'open support ticket', 'open support tickets', route('support.tickets.index'), 'bell', true);
        }
        if ($user->can('sales.manage')) {
            $add(Lead::query()->open()->whereNotNull('next_action_date')->where('next_action_date', '<=', $today)->count(),
                'lead follow-up due', 'lead follow-ups due', route('leads.index'), 'users', true);
        }
        if ($user->can('careers.manage')) {
            $add(JobApplication::where('status', 'new')->count(), 'new job application', 'new job applications', route('careers.applications'), 'document');
        }
        if ($user->can('finance')) {
            $add(Invoice::query()->overdue()->count(), 'invoice overdue for payment', 'invoices overdue for payment', route('invoices.index'), 'cash', true);
        }
        if ($workspace && $user->can('clients.view')) {
            $due = Renewal::query()->remindable()->where('renewal_date', '<=', $soon)->count()
                + HostingRecord::query()->remindable()->where('next_payment_date', '<=', $soon)->count();
            $add($due, 'renewal due within 7 days or overdue', 'renewals due within 7 days or overdue', route('renewals.index'), 'server', true);
        }

        // Urgent things first.
        usort($items, fn ($a, $b) => [$b['urgent'], $b['count']] <=> [$a['urgent'], $a['count']]);

        return $items;
    }
}
