<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Contracts\Queue\ShouldQueue;

/** To the team: a new ticket arrived, or the customer replied. */
class SupportTicketActivity extends CrmNotification implements ShouldQueue
{
    /** @param  string  $event  created | customer_replied */
    public function __construct(public SupportTicket $ticket, public string $event) {}

    public function category(): string
    {
        return 'support';
    }

    public function title(): string
    {
        return $this->event === 'created'
            ? "New support ticket {$this->ticket->number}"
            : "Customer replied on {$this->ticket->number}";
    }

    public function message(): string
    {
        $who = $this->ticket->portalUser?->name ?? 'A customer';
        $type = SupportTicket::TYPES[$this->ticket->type] ?? $this->ticket->type;

        return $this->event === 'created'
            ? "{$who} submitted a {$type}: {$this->ticket->subject}"
            : "{$who} replied: {$this->ticket->subject}";
    }

    public function details(): array
    {
        return array_filter([
            'Ticket' => $this->ticket->number,
            'From' => trim(($this->ticket->portalUser?->name ?? '').' <'.($this->ticket->portalUser?->email ?? '').'>'),
            'Type' => SupportTicket::TYPES[$this->ticket->type] ?? $this->ticket->type,
            'Priority' => SupportTicket::PRIORITIES[$this->ticket->priority] ?? $this->ticket->priority,
            'About' => $this->ticket->related_work,
        ]);
    }

    public function url(): string
    {
        return route('support.tickets.show', $this->ticket);
    }

    public function clientId(): ?int
    {
        return $this->ticket->client_id;
    }

    public function actionLabel(): string
    {
        return 'Open ticket';
    }
}
