<?php

namespace App\Actions\Absences;

use App\Enums\EmployeeStatus;
use App\Models\Employee;
use Carbon\CarbonInterface;

class SyncEmployeeAbsenceStatus
{
    /**
     * Statuses the absence registry may set or clear. Inactive and
     * terminated are HR decisions it never overrides.
     */
    private const MANAGED = [
        EmployeeStatus::Active,
        EmployeeStatus::Vacation,
        EmployeeStatus::BusinessTrip,
        EmployeeStatus::SickLeave,
    ];

    /**
     * Puts the employee's status in line with whatever absence covers
     * $date: vacation/business trip/sick leave while one is in effect,
     * back to active once none is.
     *
     * An employee with no absence records at all is left alone, so a
     * status HR set by hand before this registry existed isn't wiped out.
     */
    public function handle(Employee $employee, CarbonInterface $date): void
    {
        if (! in_array($employee->status, self::MANAGED, true)) {
            return;
        }

        $covering = $employee->absences()->notCancelled()->covering($date)->first();

        if ($covering) {
            $target = $covering->type->employeeStatus() ?? EmployeeStatus::Active;
        } elseif ($employee->status !== EmployeeStatus::Active && $employee->absences()->exists()) {
            $target = EmployeeStatus::Active;
        } else {
            return;
        }

        if ($employee->status !== $target) {
            $employee->update(['status' => $target]);
        }
    }
}
