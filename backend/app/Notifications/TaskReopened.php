<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskReopened extends Notification
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
            'title' => 'Topshiriq qayta ochildi',
            'message' => "\"{$this->task->title}\" topshirig'i qayta ko'rib chiqish uchun qaytarildi.",
            'task_id' => $this->task->id,
        ];
    }
}
