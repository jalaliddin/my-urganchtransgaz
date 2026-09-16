<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestDepartmentApproved extends Notification
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
            'title' => 'So\'rovingiz bo\'lim rahbari tomonidan tasdiqlandi',
            'message' => 'So\'rovingiz endi HR/administrator ko\'rib chiqishini kutmoqda.',
            'leave_request_id' => $this->leaveRequest->id,
        ];
    }
}
