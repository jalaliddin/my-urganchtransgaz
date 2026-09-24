<?php

namespace App\Notifications;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to a department manager (never the absent employee themselves —
 * they already know) when the nightly sweep finds an unexcused absence.
 */
class AttendanceIssue extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Employee $employee,
        private readonly string $date,
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
            'title' => 'Davomat muammosi',
            'message' => "{$this->employee->fullName()} {$this->date} kuni ishga kelmadi.",
            'employee_id' => $this->employee->id,
            'date' => $this->date,
        ];
    }
}
