<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for internal notifications. $channels decides whether a given instance
 * goes to the notification center ("database"), email ("mail") or both.
 */
abstract class CrmNotification extends Notification
{
    use Queueable;

    /** @var string[] */
    public array $channels = ['database', 'mail'];

    public function only(string $channel): static
    {
        $this->channels = [$channel];

        return $this;
    }

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    /** client | hosting | invoice */
    abstract public function category(): string;

    abstract public function title(): string;

    abstract public function message(): string;

    abstract public function url(): string;

    /** @return array<string, string> label => value rows shown in the email */
    public function details(): array
    {
        return [];
    }

    public function clientId(): ?int
    {
        return null;
    }

    public function actionLabel(): string
    {
        return 'Open in CRM';
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category(),
            'title' => $this->title(),
            'message' => $this->message(),
            'url' => $this->url(),
            'client_id' => $this->clientId(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title())->greeting($this->title())->line($this->message());
        foreach ($this->details() as $label => $value) {
            $mail->line("**{$label}:** {$value}");
        }

        return $mail->action($this->actionLabel(), $this->url());
    }
}
