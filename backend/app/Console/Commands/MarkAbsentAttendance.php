<?php

namespace App\Console\Commands;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Enums\EmployeeStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Setting;
use App\Notifications\AttendanceIssue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('attendance:mark-absentees')]
#[Description('For every currently-employed staff member with no attendance record for yesterday, create one — absent, or excused by a recorded absence or their vacation/business-trip/sick-leave status.')]
class MarkAbsentAttendance extends Command
{
    /**
     * Runs for "yesterday" (not "today"), scheduled just after midnight —
     * a day is only truly absent once it has fully elapsed, and running
     * against a day still in progress would mark people absent before
     * they've had a chance to check in.
     *
     * Skips non-working days entirely (Settings-backed, default Mon-Fri)
     * — recording "absent" for a Saturday nobody was expected to work is
     * meaningless noise, and there was previously no "working day"
     * concept anywhere in the app.
     */
    public function handle(): int
    {
        $yesterday = Carbon::yesterday();
        $workingDays = Setting::get('attendance.working_days', [1, 2, 3, 4, 5]);

        if (! in_array($yesterday->isoWeekday(), $workingDays, true)) {
            return self::SUCCESS;
        }

        $date = $yesterday->toDateString();

        Employee::query()
            ->whereIn('status', [
                EmployeeStatus::Active,
                EmployeeStatus::Vacation,
                EmployeeStatus::BusinessTrip,
                EmployeeStatus::SickLeave,
            ])
            ->whereDoesntHave('attendanceRecords', fn ($query) => $query->where('date', $date))
            ->with([
                'department.manager.user',
                'absences' => fn ($query) => $query->notCancelled()->covering($yesterday),
            ])
            ->withExists('absences as has_absence_records')
            ->chunkById(200, function ($employees) use ($date) {
                foreach ($employees as $employee) {
                    // A recorded absence decides the day. The employee's
                    // status only stands in for people whose leave was set
                    // by hand with no record behind it — for anyone else
                    // it describes today, not yesterday (an absence that
                    // starts today has already switched it).
                    $absence = $employee->absences->first();
                    $isUnexcused = ! $absence
                        && ($employee->status === EmployeeStatus::Active || $employee->has_absence_records);

                    AttendanceRecord::create([
                        'employee_id' => $employee->id,
                        'date' => $date,
                        'status' => match (true) {
                            $absence !== null => $absence->type->attendanceStatus(),
                            $isUnexcused => AttendanceStatus::Absent,
                            default => AttendanceStatus::from($employee->status->value),
                        },
                        'source' => AttendanceSource::System,
                        'employee_absence_id' => $absence?->id,
                    ]);

                    // Only an unexcused absence is an "issue" worth a
                    // manager's attention — vacation/business-trip/sick
                    // leave are already-known, approved statuses.
                    $manager = $employee->department?->manager;

                    if ($isUnexcused && $manager && $manager->id !== $employee->id && $manager->user) {
                        $manager->user->notify(new AttendanceIssue($employee, $date));
                    }
                }
            });

        return self::SUCCESS;
    }
}
