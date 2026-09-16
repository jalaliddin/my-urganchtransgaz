<?php

namespace App\Notifications;

use App\Models\EmployeeKpi;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class KpiPublished extends Notification
{
    use Queueable;

    public function __construct(private readonly EmployeeKpi $employeeKpi)
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
            'title' => 'KPI natijasi e\'lon qilindi',
            'message' => "\"{$this->employeeKpi->indicator->name}\" ko'rsatkichi bo'yicha natijangiz: {$this->employeeKpi->score}%.",
            'employee_kpi_id' => $this->employeeKpi->id,
        ];
    }
}
