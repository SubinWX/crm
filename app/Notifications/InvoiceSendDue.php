<?php

namespace App\Notifications;

use App\Models\Invoice;

class InvoiceSendDue extends CrmNotification
{
    public function __construct(public Invoice $invoice) {}

    public function category(): string
    {
        return 'invoice';
    }

    public function title(): string
    {
        return "Invoice {$this->invoice->number} is ready to send";
    }

    public function message(): string
    {
        return sprintf(
            'Draft invoice %s for %s (%s) was planned to be sent on %s. Review it and send it to the client.',
            $this->invoice->number,
            $this->invoice->client->name,
            money($this->invoice->total, $this->invoice->currency),
            fmt_date($this->invoice->planned_send_date)
        );
    }

    public function details(): array
    {
        return [
            'Client' => $this->invoice->client->name,
            'Invoice' => $this->invoice->number,
            'Total' => money($this->invoice->total, $this->invoice->currency),
            'Planned send date' => fmt_date($this->invoice->planned_send_date),
        ];
    }

    public function url(): string
    {
        return route('invoices.show', $this->invoice);
    }

    public function clientId(): ?int
    {
        return $this->invoice->client_id;
    }

    public function actionLabel(): string
    {
        return 'Review invoice';
    }
}
