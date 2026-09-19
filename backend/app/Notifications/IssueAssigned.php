<?php

namespace App\Notifications;

use App\Models\Issue;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IssueAssigned extends Notification
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
            'title' => 'Sizga muammo biriktirildi',
            'message' => "\"{$this->issue->title}\" muammosiga siz mas'ul etib tayinlandingiz.",
            'issue_id' => $this->issue->id,
        ];
    }
}
