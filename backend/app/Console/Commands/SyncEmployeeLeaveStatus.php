<?php

namespace App\Console\Commands;

use App\Enums\BusinessTripStatus;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveRequestType;
use App\Models\Employee;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('leave:sync-employee-status')]
#[Description('Recompute each employee\'s status from approved leave requests and scheduled business trips covering today, reverting to active once the range passes.')]
class SyncEmployeeLeaveStatus extends Command
{
    /**
     * @var array<string, EmployeeStatus>
     */
    private const TYPE_TO_STATUS = [
        LeaveRequestType::Vacation->value => EmployeeStatus::Vacation,
        LeaveRequestType::BusinessTrip->value => EmployeeStatus::BusinessTrip,
        LeaveRequestType::SickLeave->value => EmployeeStatus::SickLeave,
    ];

    /**
     * Recomputed fresh every run (like MarkAbsentAttendance) rather than
     * tracked as incremental start/end transitions, so an edited or
     * cancelled request/trip is always reflected correctly the next time
     * this runs — never leaves an employee's status stale from drift.
     */
    public function handle(): int
    {
        $today = Carbon::today()->toDateString();

        Employee::query()
            ->whereIn('status', [
                EmployeeStatus::Active,
                EmployeeStatus::Vacation,
                EmployeeStatus::BusinessTrip,
                EmployeeStatus::SickLeave,
            ])
            ->with([
                'leaveRequests' => fn ($query) => $query
                    ->where('status', LeaveRequestStatus::Approved)
                    ->where('start_date', '<=', $today)
                    ->where('end_date', '>=', $today),
                'businessTrips' => fn ($query) => $query
                    ->where('status', BusinessTripStatus::Scheduled)
                    ->where('start_date', '<=', $today)
                    ->where('end_date', '>=', $today),
            ])
            ->chunkById(200, function ($employees) {
                foreach ($employees as $employee) {
                    $desired = $this->desiredStatus($employee);

                    if ($employee->status !== $desired) {
                        $employee->update(['status' => $desired]);
                    }
                }
            });

        return self::SUCCESS;
    }

    private function desiredStatus(Employee $employee): EmployeeStatus
    {
        $activeRequest = $employee->leaveRequests->first(
            fn ($leaveRequest) => array_key_exists($leaveRequest->type->value, self::TYPE_TO_STATUS)
        );

        if ($activeRequest) {
            return self::TYPE_TO_STATUS[$activeRequest->type->value];
        }

        if ($employee->businessTrips->isNotEmpty()) {
            return EmployeeStatus::BusinessTrip;
        }

        return EmployeeStatus::Active;
    }
}
