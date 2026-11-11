<?php

namespace App\Notifications\Portal;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Email to the customer: ticket received, a reply from the team, or an account created for them.
 */
class TicketUpdateForCustomer extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  string  $event  received | replied */
    public function __construct(public SupportTicket $ticket, public string $event, public ?string $excerpt = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = setting('company_name', config('app.name'));
        $mail = (new MailMessage)->greeting("Hello {$notifiable->name},");

        if ($this->event === 'received') {
            $mail->subject("[{$this->ticket->number}] We received your request")
                ->line("Thanks for getting in touch. Your ticket number is **{$this->ticket->number}**.")
                ->line('Subject: '.$this->ticket->subject)
                ->line('We will reply here and by email. Quote the ticket number if you contact us another way.');
        } else {
            $mail->subject("[{$this->ticket->number}] New reply from {$company}")
                ->line("There is a new reply on your ticket **{$this->ticket->number}** ({$this->ticket->subject}).")
                ->line('Status: '.(SupportTicket::PORTAL_STATUSES[$this->ticket->status] ?? $this->ticket->status));
            if ($this->excerpt) {
                $mail->line('"'.Str::limit($this->excerpt, 300).'"');
            }
        }

        return $mail->action('View ticket', route('portal.tickets.show', $this->ticket->number));
    }
}
