<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your CutContour email verification code')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Use the verification code below to verify your email address.')
            ->line($this->code)
            ->line('This code expires in 10 minutes.')
            ->line('If you did not request this code, you can ignore this email.');
    }
}
