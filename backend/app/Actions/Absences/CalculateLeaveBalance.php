<?php

namespace App\Actions\Absences;

use App\Enums\AbsenceType;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\Setting;
use Illuminate\Support\Carbon;

class CalculateLeaveBalance
{
    /**
     * Uzbekistan's Labour Code sets annual leave at no fewer than 21
     * calendar days; organizations with a longer entitlement change it in
     * Settings.
     */
    public const DEFAULT_ANNUAL_LEAVE_DAYS = 21;

    /**
     * Annual leave taken in $year against the entitlement, plus days
     * away of every type. An absence that spans New Year counts only its
     * days inside $year.
     *
     * @return array{year: int, entitlement_days: int, used_days: int, remaining_days: int, days_by_type: array<string, int>}
     */
    public function handle(Employee $employee, int $year): array
    {
        $yearStart = Carbon::create($year)->startOfYear();
        $yearEnd = $yearStart->copy()->endOfYear()->startOfDay();

        $daysByType = array_fill_keys(array_column(AbsenceType::cases(), 'value'), 0);

        $employee->absences()
            ->notCancelled()
            ->overlapping($yearStart, $yearEnd)
            ->get()
            ->each(function (EmployeeAbsence $absence) use (&$daysByType, $yearStart, $yearEnd) {
                $from = $absence->start_date->max($yearStart);
                $to = $absence->end_date->min($yearEnd);

                $daysByType[$absence->type->value] += (int) $from->diffInDays($to) + 1;
            });

        $entitlement = (int) Setting::get('absences.annual_leave_days', self::DEFAULT_ANNUAL_LEAVE_DAYS);
        $used = $daysByType[AbsenceType::AnnualLeave->value];

        return [
            'year' => $year,
            'entitlement_days' => $entitlement,
            'used_days' => $used,
            'remaining_days' => $entitlement - $used,
            'days_by_type' => $daysByType,
        ];
    }
}
