<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Setting;
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
     *
     * Work hours/grace periods are Settings-backed (falling back to
     * config/attendance.php until an admin sets them) — the "do not
     * hard-code configurable business rules" mandate Phase 3's own
     * README flagged this as deferred to.
     */
    public function handle(?Carbon $checkIn, ?Carbon $checkOut): AttendanceStatus
    {
        $isLate = false;

        if ($checkIn) {
            $workStart = Carbon::parse($checkIn->toDateString().' '.Setting::get('attendance.work_start', config('attendance.work_start')));
            $isLate = $checkIn->greaterThan($workStart->clone()->addMinutes((int) Setting::get('attendance.late_grace_minutes', config('attendance.late_grace_minutes'))));
        }

        $isEarlyLeave = false;

        if ($checkOut) {
            $workEnd = Carbon::parse($checkOut->toDateString().' '.Setting::get('attendance.work_end', config('attendance.work_end')));
            $isEarlyLeave = $checkOut->lessThan($workEnd->clone()->subMinutes((int) Setting::get('attendance.early_leave_grace_minutes', config('attendance.early_leave_grace_minutes'))));
        }

        return match (true) {
            $isLate => AttendanceStatus::Late,
            $isEarlyLeave => AttendanceStatus::EarlyLeave,
            default => AttendanceStatus::Present,
        };
    }
}
