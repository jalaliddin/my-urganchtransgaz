<?php

namespace App\Notifications;

use App\Models\Issue;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IssueReported extends Notification
{
    use Queueable;

    public function __construct(private readonly Issue $issue)
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
            'title' => 'Yangi muammo',
            'message' => "\"{$this->issue->title}\" muammosi qayd etildi.",
            'issue_id' => $this->issue->id,
        ];
    }
}
