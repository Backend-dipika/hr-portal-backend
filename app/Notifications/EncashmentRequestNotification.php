<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EncashmentRequestNotification extends Notification
{
    use Queueable;
    protected $employeeName;
    /**
     * Create a new notification instance.
     */
    public function __construct($employeeName)
    {
        $this->employeeName = $employeeName;
        //
    }

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
            ->subject('Encashment Approval Request Submitted')
            ->greeting('Hello Admin,')
            ->line("An encashment request has been submitted by **{$this->employeeName}**.")
            ->line('Please review and take necessary action.')
            ->action('Review Request', url('/admin/encashments'))
            ->line('Thank you for managing employee requests promptly.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
