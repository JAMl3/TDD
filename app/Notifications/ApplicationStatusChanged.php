<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public JobApplication $application,
        public string $newStatus
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusMessage = match($this->newStatus) {
            'accepted' => 'Congratulations! Your application has been accepted',
            'rejected' => 'We regret to inform you that your application was not accepted',
            default => 'Your application status has been updated'
        };

        return (new MailMessage)
            ->subject('Application Status Updated')
            ->line("Job: {$this->application->job->title}")
            ->line($statusMessage)
            ->action('View Application', url("/applications/{$this->application->id}"))
            ->line('Thank you for using our platform!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'application_status_changed',
            'application_id' => $this->application->id,
            'job_id' => $this->application->job_id,
            'job_title' => $this->application->job->title,
            'old_status' => $this->application->getOriginal('status'),
            'new_status' => $this->newStatus
        ];
    }
} 