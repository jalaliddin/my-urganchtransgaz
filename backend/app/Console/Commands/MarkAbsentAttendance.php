<?php

namespace App\Console\Commands;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('attendance:mark-absentees')]
#[Description('For every currently-employed staff member with no attendance record for yesterday, create one — absent, or matching their vacation/business-trip/sick-leave status.')]
class MarkAbsentAttendance extends Command
{
    /**
     * Runs for "yesterday" (not "today"), scheduled just after midnight —
     * a day is only truly absent once it has fully elapsed, and running
     * against a day still in progress would mark people absent before
     * they've had a chance to check in.
     */
    public function handle(): int
    {
        $date = Carbon::yesterday()->toDateString();

        Employee::query()
            ->whereIn('status', [
                EmployeeStatus::Active,
                EmployeeStatus::Vacation,
                EmployeeStatus::BusinessTrip,
                EmployeeStatus::SickLeave,
            ])
            ->whereDoesntHave('attendanceRecords', fn ($query) => $query->where('date', $date))
            ->chunkById(200, function ($employees) use ($date) {
                foreach ($employees as $employee) {
                    AttendanceRecord::create([
                        'employee_id' => $employee->id,
                        'date' => $date,
                        'status' => $employee->status === EmployeeStatus::Active
                            ? AttendanceStatus::Absent
                            : AttendanceStatus::from($employee->status->value),
                        'source' => AttendanceSource::System,
                    ]);
                }
            });

        return self::SUCCESS;
    }
}
