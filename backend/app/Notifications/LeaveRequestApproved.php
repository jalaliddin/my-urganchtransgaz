<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestApproved extends Notification
{
    use Queueable;

    public function __construct(private readonly LeaveRequest $leaveRequest)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'So\'rovingiz tasdiqlandi',
            'message' => "{$this->leaveRequest->start_date->format('Y-m-d')} — {$this->leaveRequest->end_date->format('Y-m-d')} oralig'idagi so'rovingiz to'liq tasdiqlandi.",
            'leave_request_id' => $this->leaveRequest->id,
        ];
    }
}
