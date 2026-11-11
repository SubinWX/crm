<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token, public string $inviterName) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = setting('company_name', config('app.name'));

        return (new MailMessage)
            ->subject("You have been invited to {$company}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->inviterName} has invited you to join the {$company} CRM as {$notifiable->role_label}.")
            ->action('Accept invitation', route('invitation.show', $this->token))
            ->line('After that, sign in at '.$notifiable->loginUrl())
            ->line('This link expires in 7 days. If you were not expecting it, you can ignore this email.');
    }
}
