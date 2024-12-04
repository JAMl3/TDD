<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Payment $payment
    ) {
        if (!$this->payment->relationLoaded('job')) {
            $this->payment->load('job');
        }
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New Payment Created')
            ->line("A new payment has been created for job: {$this->payment->job->title}")
            ->line("Amount: $" . number_format($this->payment->amount, 2))
            ->line("Description: {$this->payment->description}")
            ->line("Due Date: {$this->payment->due_date->format('M d, Y')}")
            ->action('View Payment', url("/payments/{$this->payment->id}"))
            ->line('Thank you for using our platform!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_created',
            'payment_id' => $this->payment->id,
            'job_id' => $this->payment->job_id,
            'job_title' => $this->payment->job->title,
            'amount' => $this->payment->amount,
            'description' => $this->payment->description,
            'due_date' => $this->payment->due_date->format('Y-m-d')
        ];
    }
} 