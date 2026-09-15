<?php

namespace App\Notifications;

use App\Models\EmployeeChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProfileChangeApproved extends Notification
{
    use Queueable;

    public function __construct(private readonly EmployeeChangeRequest $changeRequest)
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
            'title' => 'So\'rov tasdiqlandi',
            'message' => 'Profilingizga oid o\'zgartirish so\'rovi tasdiqlandi.',
            'change_request_id' => $this->changeRequest->id,
        ];
    }
}
