<?php

namespace App\Notifications;

use App\Models\HostingRecord;

class HostingPaymentDue extends CrmNotification
{
    /** @param  string  $stage  two_months | seven_days */
    public function __construct(public HostingRecord $hosting, public string $stage) {}

    public function category(): string
    {
        return 'hosting';
    }

    public function title(): string
    {
        $when = $this->stage === 'seven_days' ? 'in 7 days' : 'in 2 months';

        return "Hosting payment due {$when}: {$this->hosting->domain}";
    }

    public function message(): string
    {
        return sprintf(
            '%s — %s hosting renewal of %s is due on %s.',
            $this->hosting->client->name,
            $this->hosting->domain,
            money($this->hosting->renewal_amount),
            fmt_date($this->hosting->next_payment_date)
        );
    }

    public function details(): array
    {
        return [
            'Client' => $this->hosting->client->name,
            'Domain' => $this->hosting->domain,
            'Amount' => money($this->hosting->renewal_amount),
            'Due date' => fmt_date($this->hosting->next_payment_date),
        ];
    }

    public function url(): string
    {
        return route('clients.show', ['client' => $this->hosting->client_id, 'tab' => 'renewals']);
    }

    public function clientId(): ?int
    {
        return $this->hosting->client_id;
    }

    public function actionLabel(): string
    {
        return 'View hosting record';
    }
}
