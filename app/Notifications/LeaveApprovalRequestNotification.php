<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveApprovalRequestNotification extends Notification
{
    use Queueable;

    protected $leaveRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(LeaveRequest $leaveRequest)
    {
        $this->leaveRequest = $leaveRequest;
    }

    /**
     * Get the notification's delivery channels.
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
            ->subject('New Leave Request Pending Your Approval')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($this->leaveRequest->user->name . ' has submitted a leave request.')
            ->line('Leave Type: ' . $this->leaveRequest->leaveType->name)
            ->line('Dates: ' . $this->leaveRequest->start_date . ' to ' . $this->leaveRequest->end_date)
            ->line('Reason: ' . ($this->leaveRequest->reason ?: 'N/A'))
            // ->action('Review Request', url('/leave-approvals/' . $this->leaveRequest->id))
            ->action('Review Request', 'http://localhost:5173/')
            ->line('Please review and take action.');
    }

    /**
     * Get the array representation of the notification (for database/broadcast if needed).
     */
    public function toArray(object $notifiable): array
    {
        return [
            'leave_request_id' => $this->leaveRequest->id,
            'employee'         => $this->leaveRequest->user->name,
            'leave_type'       => $this->leaveRequest->leaveType->name,
            'start_date'       => $this->leaveRequest->start_date,
            'end_date'         => $this->leaveRequest->end_date,
            'reason'           => $this->leaveRequest->reason,
            'status'           => $this->leaveRequest->status,
        ];
    }
}
