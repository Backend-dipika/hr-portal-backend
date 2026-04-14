<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class LeaveApprovalRequestNotification extends Notification  implements ShouldQueue
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
        $leaveRequest = LeaveRequest::with(['user', 'leaveType'])
            ->find($this->leaveRequest->id); 
        // Log::info('Leave request user', ['user_id' => $leaveRequest->user_id, 'user_name' => optional($leaveRequest->user)->first_name]);
        return (new MailMessage)
            ->subject('New Leave Request Pending Your Approval')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line(optional($leaveRequest->user)->first_name . ' ' . optional($leaveRequest->user)->last_name . ' has submitted a leave request.')
            ->line('Leave Type: ' . optional($leaveRequest->leaveType)->name)
            ->line('Dates: ' . $leaveRequest->start_date . ' to ' . $leaveRequest->end_date)
            ->line('Reason: ' . ($leaveRequest->reason ?: 'N/A'))
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
