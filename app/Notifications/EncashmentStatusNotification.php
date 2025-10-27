<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EncashmentStatusNotification extends Notification
{
    use Queueable;
    protected $status;
    /**
     * Create a new notification instance.
     */
    public function __construct($status)
    {
        $this->status = ucfirst($status);
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
        // return (new MailMessage)
        $mail = (new MailMessage)
            ->subject("Encashment Request {$this->status}")
            ->greeting("Hello {$notifiable->name},");

        if ($this->status === 'Approved') {
            $mail->line("Good news! Your encashment request has been approved futher process will be handle by Shivanad sir.")
                ->line('The approved amount will be processed shortly.');
        } else {
            $mail->line("We regret to inform you that your encashment request has been rejected your pending leaves will get carry forward.")
                ->line('For more information, please contact the HR department.');
        }

        return $mail->line('Thank you for your understanding.');
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
