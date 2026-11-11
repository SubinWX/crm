<?php

namespace App\Notifications;

use App\Models\Renewal;

class RenewalDue extends CrmNotification
{
    public function __construct(public Renewal $renewal, public int $daysBefore) {}

    public function category(): string
    {
        return 'renewal';
    }

    public function title(): string
    {
        $type = Renewal::TYPES[$this->renewal->type] ?? 'Renewal';

        return "{$type} renewal due in {$this->daysBefore} days: {$this->renewal->name}";
    }

    public function message(): string
    {
        return sprintf('%s — %s renews on %s (%s).', $this->renewal->client->name, $this->renewal->name, fmt_date($this->renewal->renewal_date), money($this->renewal->amount));
    }

    public function details(): array
    {
        return array_filter([
            'Client' => $this->renewal->client->name, 'Renewal' => $this->renewal->name, 'Type' => Renewal::TYPES[$this->renewal->type] ?? $this->renewal->type,
            'Provider' => $this->renewal->provider, 'Amount' => money($this->renewal->amount), 'Renewal date' => fmt_date($this->renewal->renewal_date),
        ]);
    }

    public function url(): string
    {
        return route('clients.show', ['client' => $this->renewal->client_id, 'tab' => 'renewals']);
    }

    public function clientId(): ?int
    {
        return $this->renewal->client_id;
    }

    public function actionLabel(): string
    {
        return 'View renewal';
    }
}
