<?php

namespace App\Notifications;

use App\Models\Exam;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ExamAvailable extends Notification
{
    use Queueable;

    /**
     * @param  int  $threshold  Days until the exam's start_date (3 or 1).
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
        return [
            'title' => 'Yaqinlashib kelayotgan imtihon',
            'message' => "\"{$this->exam->title}\" imtihoni {$this->threshold} kundan so'ng boshlanadi.",
            'exam_id' => $this->exam->id,
            'threshold' => $this->threshold,
        ];
    }
}
