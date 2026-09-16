<?php

namespace App\Notifications;

use App\Models\Exam;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExamFailed extends Notification
{
    use Queueable;

    /**
     * @param  int  $remainingAttempts  Covers both "failed exam" and
     *                                  "retake availability" (§37) in one notification, since they
     *                                  happen at the same moment.
     */
    public function __construct(
        private readonly Exam $exam,
        private readonly int $remainingAttempts,
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
        $message = $this->remainingAttempts > 0
            ? "\"{$this->exam->title}\" imtihonidan o'ta olmadingiz. Sizda yana {$this->remainingAttempts} ta urinish qoldi."
            : "\"{$this->exam->title}\" imtihonidan o'ta olmadingiz. Urinishlar soni tugadi.";

        return [
            'title' => 'Imtihon natijasi',
            'message' => $message,
            'exam_id' => $this->exam->id,
        ];
    }
}
