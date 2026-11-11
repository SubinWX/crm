<?php

namespace App\Notifications\Portal;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortalResetPassword extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your support portal password')
            ->greeting("Hello {$notifiable->name},")
            ->line('Use the button below to choose a new password for the '.setting('company_name', config('app.name')).' support portal.')
            ->action('Reset password', route('portal.password.reset', ['token' => $this->token, 'email' => $notifiable->email]))
            ->line('This link expires in 60 minutes. If you did not ask for it, you can ignore this email.');
    }
}
