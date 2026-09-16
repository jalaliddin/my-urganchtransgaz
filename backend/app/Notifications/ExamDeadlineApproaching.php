<?php

namespace App\Notifications;

use App\Models\Exam;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExamDeadlineApproaching extends Notification
{
    use Queueable;

    /**
     * @param  int  $threshold  Days remaining until end_date (3, 1, or 0
     *                          for "closes today").
     */
    public function __construct(
        private readonly Exam $exam,
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
            ? "\"{$this->exam->title}\" imtihonini topshirish muddati bugun tugaydi."
            : "\"{$this->exam->title}\" imtihonini topshirish muddati {$this->threshold} kundan so'ng tugaydi.";

        return [
            'title' => 'Imtihon muddati',
            'message' => $message,
            'exam_id' => $this->exam->id,
            'threshold' => $this->threshold,
        ];
    }
}
