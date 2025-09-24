<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;


class AppreciationNotification extends Notification
{
    use Queueable;
    protected $messageData;

    /**
     * Create a new notification instance.
     */
    public function __construct($messageData)
    {
        $this->messageData = $messageData;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('You received an appreciation!')
            ->line($this->messageData['message'] . ' for ' . $this->messageData['category'])
            ->action('View Appreciation', config('app.frontend_url'))
            ->line('Thank you for your contribution!');
    }

    public function toDatabase($notifiable)
    {
        return [
            'from_user_id' => $this->messageData['from_user_id'],
            'to_user_id' => $this->messageData['to_user_id'],
            'title' => $this->messageData['title'],
            'message' => $this->messageData['message'],
        ];
    }

    // public function toBroadcast($notifiable)
    // {
    // }
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
