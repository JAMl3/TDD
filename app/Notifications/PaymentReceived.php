<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Payment $payment
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Received')
            ->line("Payment has been received for job: {$this->payment->job->title}")
            ->line("Amount: $" . number_format($this->payment->amount, 2))
            ->line("Description: {$this->payment->description}")
            ->line("Transaction ID: {$this->payment->transaction_id}")
            ->action('View Payment', url("/payments/{$this->payment->id}"))
            ->line('Thank you for using our platform!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'payment_received',
            'payment_id' => $this->payment->id,
            'job_id' => $this->payment->job_id,
            'job_title' => $this->payment->job->title,
            'amount' => $this->payment->amount,
            'description' => $this->payment->description,
            'transaction_id' => $this->payment->transaction_id,
            'paid_at' => $this->payment->paid_at->format('Y-m-d H:i:s')
        ];
    }
} 