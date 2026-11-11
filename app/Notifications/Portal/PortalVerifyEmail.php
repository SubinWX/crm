<?php

namespace App\Notifications\Portal;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PortalVerifyEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public const EXPIRES_HOURS = 48;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** Signed link tied to this account and this email address; it stops working if either changes. */
    public static function url(object $notifiable): string
    {
        return URL::temporarySignedRoute('portal.verify', now()->addHours(self::EXPIRES_HOURS), [
            'id' => $notifiable->getKey(), 'hash' => sha1($notifiable->email),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your email for the support portal')
            ->greeting("Hello {$notifiable->name},")
            ->line('Please confirm your email address so you can submit and follow support tickets with '.setting('company_name', config('app.name')).'.')
            ->action('Confirm email address', self::url($notifiable))
            ->line('This link works for '.self::EXPIRES_HOURS.' hours. If you did not create an account, you can ignore this email.');
    }
}
