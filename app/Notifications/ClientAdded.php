<?php

namespace App\Notifications;

use App\Models\Client;
use Illuminate\Contracts\Queue\ShouldQueue;

class ClientAdded extends CrmNotification implements ShouldQueue
{
    public function __construct(public Client $client, public string $addedBy) {}

    public function category(): string
    {
        return 'client';
    }

    public function title(): string
    {
        return 'New client added: '.$this->client->name;
    }

    public function message(): string
    {
        return "{$this->addedBy} added {$this->client->name} as a client.";
    }

    public function details(): array
    {
        return ['Client' => $this->client->name, 'Added by' => $this->addedBy];
    }

    public function url(): string
    {
        return route('clients.show', $this->client);
    }

    public function clientId(): ?int
    {
        return $this->client->id;
    }

    public function actionLabel(): string
    {
        return 'View client profile';
    }
}
