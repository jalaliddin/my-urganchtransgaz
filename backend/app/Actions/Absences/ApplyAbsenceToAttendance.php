<?php

namespace App\Actions\Absences;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\EmployeeAbsence;
use Illuminate\Support\Carbon;

class ApplyAbsenceToAttendance
{
    /**
     * Past days this absence covers that were recorded as unexcused
     * (typically a sick leave certificate handed in after the fact)
     * become excused and are linked back to it. Days the employee
     * actually came in are left as they are. Today and later days are
     * picked up by attendance:mark-absentees once each one ends.
     */
    public function apply(EmployeeAbsence $absence): void
    {
        if ($absence->isCancelled()) {
            return;
        }

        $yesterday = Carbon::yesterday();

        if ($absence->start_date->gt($yesterday)) {
            return;
        }

        $lastPastDay = $absence->end_date->lt($yesterday) ? $absence->end_date : $yesterday;

        AttendanceRecord::query()
            ->where('employee_id', $absence->employee_id)
            ->whereBetween('date', [$absence->start_date->toDateString(), $lastPastDay->toDateString()])
            ->where('status', AttendanceStatus::Absent)
            ->update([
                'status' => $absence->type->attendanceStatus(),
                'employee_absence_id' => $absence->id,
            ]);
    }

    /**
     * Puts every day this absence excused back to unexcused.
     */
    public function revert(EmployeeAbsence $absence): void
    {
        $absence->attendanceRecords()->update([
            'status' => AttendanceStatus::Absent,
            'employee_absence_id' => null,
        ]);
    }
}
