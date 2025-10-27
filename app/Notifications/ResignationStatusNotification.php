<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResignationStatusNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $employeeName;
    protected $status;
    protected $remarks;
    protected $lastWorkingDate;

    /**
     * Create a new notification instance.
     */
    public function __construct($employeeName, $status, $remarks = null, $lastWorkingDate = null)
    {
        $this->employeeName = $employeeName;
        $this->status = strtolower($status); // "approved" or "rejected"
        $this->remarks = $remarks;
        $this->lastWorkingDate = $lastWorkingDate;
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
        $mail = new MailMessage();

        if ($this->status === 'approved') {
            $mail->subject('Resignation Approved')
                ->greeting("Hello {$this->employeeName},")
                ->line('We would like to inform you that your resignation request has been **approved**.')
                ->when($this->lastWorkingDate, function ($msg) {
                    $msg->line('**Your confirmed last working date:** ' . date('d M Y', strtotime($this->lastWorkingDate)));
                })
                ->line('Please ensure that all handover and exit formalities are completed before your final working day.')
                ->line('We appreciate your contributions to the organization and wish you all the best in your future endeavors.');
        } else {
            $mail->subject('Resignation Rejected')
                ->greeting("Hello {$this->employeeName},")
                ->line('We would like to inform you that your resignation request has been **rejected**.')
                ->when($this->remarks, function ($msg) {
                    $msg->line('**Remarks:** ' . $this->remarks);
                })
                ->line('If you would like to discuss this further, please contact your reporting manager or HR department.');
        }

        return $mail->line('Thank you.');
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
