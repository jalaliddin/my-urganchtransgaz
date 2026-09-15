<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Support\Carbon;

class CalculateAttendanceStatus
{
    /**
     * Derive a day's attendance status from its check-in/check-out times
     * against the configured work hours. Late takes priority over early
     * leave when a day is both, since a single record can only hold one
     * status. Never returns Absent — that status is only ever assigned by
     * the nightly "no record at all for the day" sweep
     * (`attendance:mark-absentees`), not by this calculator.
     */
    public function handle(?Carbon $checkIn, ?Carbon $checkOut): AttendanceStatus
    {
        $isLate = false;

        if ($checkIn) {
            $workStart = Carbon::parse($checkIn->toDateString().' '.config('attendance.work_start'));
            $isLate = $checkIn->greaterThan($workStart->clone()->addMinutes((int) config('attendance.late_grace_minutes')));
        }

        $isEarlyLeave = false;

        if ($checkOut) {
            $workEnd = Carbon::parse($checkOut->toDateString().' '.config('attendance.work_end'));
            $isEarlyLeave = $checkOut->lessThan($workEnd->clone()->subMinutes((int) config('attendance.early_leave_grace_minutes')));
        }

        return match (true) {
            $isLate => AttendanceStatus::Late,
            $isEarlyLeave => AttendanceStatus::EarlyLeave,
            default => AttendanceStatus::Present,
        };
    }
}
