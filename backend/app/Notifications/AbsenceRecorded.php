<?php

namespace App\Notifications;

use App\Models\EmployeeAbsence;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AbsenceRecorded extends Notification
{
    use Queueable;

    public function __construct(
        private readonly EmployeeAbsence $absence,
        private readonly bool $forManager,
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
        $period = $this->absence->start_date->format('d.m.Y').' — '.$this->absence->end_date->format('d.m.Y');
        $type = $this->absence->type->label();

        return [
            'title' => $type,
            'message' => $this->forManager
                ? "{$this->absence->employee->fullName()}: {$type}, {$period}."
                : "Sizga {$type} rasmiylashtirildi: {$period}.",
            'absence_id' => $this->absence->id,
            'employee_id' => $this->absence->employee_id,
        ];
    }
}
