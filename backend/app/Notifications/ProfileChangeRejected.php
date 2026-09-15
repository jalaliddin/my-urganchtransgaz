<?php

namespace App\Notifications;

use App\Models\EmployeeChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProfileChangeRejected extends Notification
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
            'title' => 'So\'rov rad etildi',
            'message' => "Profilingizga oid o'zgartirish so'rovi rad etildi: {$this->changeRequest->review_comment}",
            'change_request_id' => $this->changeRequest->id,
        ];
    }
}
