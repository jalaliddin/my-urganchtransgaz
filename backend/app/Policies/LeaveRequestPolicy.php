<?php

namespace App\Policies;

use App\Enums\LeaveRequestStatus;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationScope;

class LeaveRequestPolicy
{
    use ChecksOrganizationScope;

    /**
     * Every authenticated user may list requests; the controller scopes
     * the query to "my requests" for a plain employee and to the scoped
     * review queue for department-manager/HR/admin roles.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->employee?->id === $leaveRequest->employee_id) {
            return true;
        }

        $employee = $leaveRequest->employee;

        if ($user->can('leave_requests.review') && $this->withinScope($user, $employee->organization_id, $employee->department_id)) {
            return true;
        }

        return $user->can('leave_requests.approve')
            && $this->withinScope($user, $employee->organization_id, $employee->department_id);
    }

    /**
     * Submitting a request always targets the requester's own record —
     * scope is irrelevant, same reasoning as EmployeeChangeRequestPolicy.
     */
    public function create(User $user): bool
    {
        return $user->employee !== null;
    }

    /**
     * Stage 1 — the employee's own department manager.
     */
    public function departmentReview(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($leaveRequest->status !== LeaveRequestStatus::Pending) {
            return false;
        }

        $employee = $leaveRequest->employee;

        return $user->can('leave_requests.review')
            && $this->withinScope($user, $employee->organization_id, $employee->department_id);
    }

    /**
     * Stage 2 (final) — HR/organization-admin/central. Valid from
     * `pending` too, covering the no-department-manager auto-skip case
     * where the request never passes through department_approved at all
     * — wait, that case is handled at creation (status starts at
     * department_approved directly), so this only ever sees
     * department_approved in practice; pending is accepted defensively.
     */
    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! in_array($leaveRequest->status, [LeaveRequestStatus::Pending, LeaveRequestStatus::DepartmentApproved], true)) {
            return false;
        }

        $employee = $leaveRequest->employee;

        return $user->can('leave_requests.approve')
            && $this->withinScope($user, $employee->organization_id, $employee->department_id);
    }

    /**
     * An employee may cancel their own request while it's still in
     * flight — not once it's been finally decided.
     */
    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->employee?->id === $leaveRequest->employee_id
            && in_array($leaveRequest->status, [LeaveRequestStatus::Pending, LeaveRequestStatus::DepartmentApproved], true);
    }
}
