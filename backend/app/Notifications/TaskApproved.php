<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskApproved extends Notification
{
    use Queueable;

    public function __construct(private readonly Task $task)
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
            'title' => 'Topshiriq tasdiqlandi',
            'message' => "\"{$this->task->title}\" topshirig'ining bajarilishi tasdiqlandi.",
            'task_id' => $this->task->id,
        ];
    }
}
