<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationScope;

class AttendanceRecordPolicy
{
    use ChecksOrganizationScope;

    /**
     * Every authenticated user may list attendance; the controller scopes
     * the query to "own records" for a plain employee and to permitted
     * organizations/departments for everyone else.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AttendanceRecord $record): bool
    {
        if ($user->employee?->id === $record->employee_id) {
            return true;
        }

        return $user->can('attendance.view')
            && $this->withinScope($user, $record->employee->organization_id, $record->employee->department_id);
    }

    /**
     * Manual corrections (create/update/delete a record on someone's
     * behalf) always require `attendance.manage` plus scope — self-service
     * check-in/check-out never goes through this policy, since it always
     * targets the authenticated user's own employee record.
     */
    public function update(User $user, AttendanceRecord $record): bool
    {
        return $user->can('attendance.manage')
            && $this->withinScope($user, $record->employee->organization_id, $record->employee->department_id);
    }

    public function delete(User $user, AttendanceRecord $record): bool
    {
        return $user->can('attendance.manage')
            && $this->withinScope($user, $record->employee->organization_id, $record->employee->department_id);
    }
}
