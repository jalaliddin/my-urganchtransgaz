<?php

namespace App\Actions\LeaveRequests;

use App\Enums\EmployeeStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveRequestType;
use App\Models\LeaveRequest;
use App\Models\User;

class ApproveLeaveRequest
{
    /**
     * Maps a leave type to the Employee status attendance already reads
     * (MarkAbsentAttendance). `other` has no attendance-visible meaning.
     *
     * @var array<string, EmployeeStatus>
     */
    private const STATUS_MAP = [
        LeaveRequestType::Vacation->value => EmployeeStatus::Vacation,
        LeaveRequestType::BusinessTrip->value => EmployeeStatus::BusinessTrip,
        LeaveRequestType::SickLeave->value => EmployeeStatus::SickLeave,
    ];

    public function handle(LeaveRequest $leaveRequest, User $approver): LeaveRequest
    {
        $leaveRequest->update([
            'status' => LeaveRequestStatus::Approved,
            'hr_reviewed_by' => $approver->id,
            'hr_reviewed_at' => now(),
        ]);

        // The daily leave:sync-employee-status command handles requests
        // that start in the future; this only covers the case where
        // today already falls inside the approved range, so the effect
        // is visible immediately rather than waiting for the next run.
        $mappedStatus = self::STATUS_MAP[$leaveRequest->type->value] ?? null;

        if ($mappedStatus !== null && $leaveRequest->coversToday()) {
            $employee = $leaveRequest->employee;

            if ($employee->status === EmployeeStatus::Active) {
                $employee->update(['status' => $mappedStatus]);
            }
        }

        return $leaveRequest;
    }
}
