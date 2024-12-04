<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public JobApplication $application
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Application Received')
            ->line("A new application has been submitted for your job: {$this->application->job->title}")
            ->line("Developer: {$this->application->user->name}")
            ->line("Proposed Budget: $" . number_format($this->application->budget, 2))
            ->line("Timeline: {$this->application->timeline} days")
            ->action('View Application', url("/applications/{$this->application->id}"))
            ->line('Thank you for using our platform!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_submitted',
            'application_id' => $this->application->id,
            'job_id' => $this->application->job_id,
            'developer_name' => $this->application->user->name,
            'job_title' => $this->application->job->title,
            'budget' => $this->application->budget,
            'timeline' => $this->application->timeline
        ];
    }
} 