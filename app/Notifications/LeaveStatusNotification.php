<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;

class LeaveStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $leaveRequest;
    protected $status;

    /**
     * Create a new notification instance.
     */
    public function __construct(LeaveRequest $leaveRequest, string $status)
    {
        $this->leaveRequest = $leaveRequest;
        $this->status = $status;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        Log::info("LeaveStatusNotification: via() called for User ID: {$this->leaveRequest->user_id}");
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Log that email is actually being generated
        Log::info("LeaveStatusNotification: Generating email for LeaveRequest ID: {$this->leaveRequest->id} for User ID: {$this->leaveRequest->user_id}");

        return (new MailMessage)
            ->subject("Leave Request {$this->status}")
            ->greeting("Hello {$this->leaveRequest->user->first_name},")
            ->line("Your leave request has been **{$this->status}**.")
            ->line("**Leave Type:** " . ($this->leaveRequest->leaveType?->name ?? 'N/A'))
            ->line("**Duration:** {$this->leaveRequest->start_date->format('d M Y')} to {$this->leaveRequest->end_date->format('d M Y')}")
            ->line("**Days Requested:** {$this->leaveRequest->total_days_requested}")
            ->line("**Days Approved:** {$this->leaveRequest->total_days_approved}")
            ->line("**Reason:** {$this->leaveRequest->reason}")
            ->line("Final Status: {$this->status}")
            ->line("Thank you for using our application!");
    }

    /**
     * Get the array representation of the notification (for database, if used).
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'leave_request_id' => $this->leaveRequest->id,
            'status' => $this->status,
            'user_id' => $this->leaveRequest->user_id,
            'leave_type' => $this->leaveRequest->leaveType?->name ?? 'N/A',
            'start_date' => $this->leaveRequest->start_date->toDateString(),
            'end_date' => $this->leaveRequest->end_date->toDateString(),
            'days_requested' => $this->leaveRequest->total_days_requested,
            'days_approved' => $this->leaveRequest->total_days_approved,
            'reason' => $this->leaveRequest->reason,
        ];
    }
}
