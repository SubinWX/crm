<?php

use Illuminate\Support\Facades\Schedule;

// Hourly rather than daily: the command is idempotent, so extra runs cost nothing and a
// missed run (server restart, deploy) is caught up within the hour.
Schedule::command('crm:send-reminders')->hourly()->withoutOverlapping();

// Recurring invoices: idempotent, so hourly catches up after any downtime.
Schedule::command('crm:generate-recurring-invoices')->hourly()->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=720')->daily();
