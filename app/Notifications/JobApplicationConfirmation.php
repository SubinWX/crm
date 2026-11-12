<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** To the applicant: we received your application. Sent to the email they typed on the form. */
class JobApplicationConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public JobApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = setting('company_name') ?: app_name();

        return (new MailMessage)
            ->subject('We received your application: '.$this->application->vacancy_title)
            ->greeting('Hello '.$this->application->name.',')
            ->line("Thank you for applying for {$this->application->vacancy_title} at {$company}. We have received your application and resume.")
            ->line('Our team will review it and contact you if your profile matches what we are looking for.')
            ->salutation("Regards,\n{$company}");
    }
}
