<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Contracts\Queue\ShouldQueue;

/** To the team: someone applied through the public careers page. */
class JobApplicationReceived extends CrmNotification implements ShouldQueue
{
    public function __construct(public JobApplication $application) {}

    public function category(): string
    {
        return 'careers';
    }

    public function title(): string
    {
        return 'New job application: '.$this->application->vacancy_title;
    }

    public function message(): string
    {
        return "{$this->application->name} applied for {$this->application->vacancy_title}.";
    }

    public function details(): array
    {
        return array_filter([
            'Applicant' => $this->application->name,
            'Email' => $this->application->email,
            'Phone' => $this->application->phone,
            'Experience' => $this->application->experience_years !== null ? (float) $this->application->experience_years.' years' : null,
            'Location' => $this->application->location,
        ]);
    }

    public function url(): string
    {
        return route('careers.applications', ['open' => $this->application->id]);
    }

    public function actionLabel(): string
    {
        return 'Open application';
    }
}
