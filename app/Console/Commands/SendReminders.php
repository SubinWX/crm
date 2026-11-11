<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    protected $signature = 'crm:send-reminders {--date= : Pretend today is this date (Y-m-d), for testing}';

    protected $description = 'Queue hosting payment and invoice planned-send reminders for all active Super Admins';

    public function handle(ReminderService $reminders): int
    {
        $today = $this->option('date') ? local_date($this->option('date')) : null;
        $stats = $reminders->run($today);

        $this->info(sprintf(
            'Reminders due: %d · deliveries queued: %d · stale deliveries re-queued: %d',
            $stats['due'], $stats['queued'], $stats['requeued']
        ));

        return self::SUCCESS;
    }
}
