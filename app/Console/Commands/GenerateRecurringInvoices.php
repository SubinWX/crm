<?php

namespace App\Console\Commands;

use App\Models\RecurringInvoice;
use Illuminate\Console\Command;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'crm:generate-recurring-invoices';

    protected $description = 'Raise the draft invoices of every recurring invoice whose date has come';

    public function handle(): int
    {
        $this->info(RecurringInvoice::generateDue().' recurring invoice(s) raised.');

        return self::SUCCESS;
    }
}
