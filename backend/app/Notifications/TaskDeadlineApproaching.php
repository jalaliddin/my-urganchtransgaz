<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskDeadlineApproaching extends Notification
{
    use Queueable;

    /**
     * @param  int  $threshold  Days remaining (3, 1, or 0 for "due today").
     */
    public function __construct(
        private readonly Task $task,
        private readonly int $threshold,
    ) {
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
        $message = $this->threshold === 0
            ? "\"{$this->task->title}\" topshirig'ining muddati bugun tugaydi."
            : "\"{$this->task->title}\" topshirig'ining muddati {$this->threshold} kundan so'ng tugaydi.";

        return [
            'title' => 'Topshiriq muddati',
            'message' => $message,
            'task_id' => $this->task->id,
            'threshold' => $this->threshold,
        ];
    }
}
