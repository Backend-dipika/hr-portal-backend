<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class YearEndConfirmationNotification extends Notification
{
    use Queueable;
    // protected $messageData;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        // $this->messageData = $messageData;
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
            ->subject('Action Required: Year-End Leave Processing')
            ->greeting('Hello '.',')
            ->line('The year-end leave cycle is now open for you.')
            ->line('Please review your remaining leave balance for the year and choose your action (Carry Forward or Encash) Asap.')
            ->action('Take Year-End Action', url('/encashment')) // Link to frontend page
            ->line('Your timely action will help HR process leave balances accurately.')
            ->line('Thank you for your cooperation!');
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
