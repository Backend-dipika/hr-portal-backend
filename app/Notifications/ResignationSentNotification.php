<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResignationSentNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $employeeName;
    protected $personalEmail;
    protected $expectedLastWorkingDate;
    protected $messageContent;

    /**
     * Create a new notification instance.
     */
    public function __construct($employeeName, $personalEmail, $expectedLastWorkingDate, $messageContent = null)
    {
        $this->employeeName = $employeeName;
        $this->personalEmail = $personalEmail;
        $this->expectedLastWorkingDate = $expectedLastWorkingDate;
        $this->messageContent = $messageContent;
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
          ->subject('Resignation Submitted by ' . $this->employeeName)
            ->greeting('Hello Admin,')
            ->line('A new resignation has been submitted by an employee. Below are the details:')
            ->line('**Employee Name:** ' . $this->employeeName)
            ->line('**Personal Email:** ' . $this->personalEmail)
            ->line('**Last Expected Working Date:** ' . date('d M Y', strtotime($this->expectedLastWorkingDate)))
            ->when($this->messageContent, function ($mail) {
                return $mail->line('**Employee Note:** ' . $this->messageContent);
            })
            ->action('View Resignation Details', url('/admin/resignations'))
            ->line('Please take the necessary action.')
            ->line('Thank you.');
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
