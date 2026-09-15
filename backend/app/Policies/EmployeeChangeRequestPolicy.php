<?php

namespace App\Policies;

use App\Models\EmployeeChangeRequest;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationScope;

class EmployeeChangeRequestPolicy
{
    use ChecksOrganizationScope;

    /**
     * Every authenticated user may list change requests; the controller
     * scopes the query to "my requests" for a plain employee and to the
     * pending review queue (within scope) for HR/admin roles.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EmployeeChangeRequest $changeRequest): bool
    {
        if ($user->employee?->id === $changeRequest->employee_id) {
            return true;
        }

        $employee = $changeRequest->employee;

        return $user->can('employees.update')
            && $this->withinScope($user, $employee->organization_id, $employee->department_id);
    }

    /**
     * A request to change one's own profile is always allowed to submit;
     * scope is irrelevant since it targets the requester's own record.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can approve or reject the request.
     * Reuses employees.update — approving a change request is, in effect,
     * updating the employee record.
     */
    public function review(User $user, EmployeeChangeRequest $changeRequest): bool
    {
        $employee = $changeRequest->employee;

        return $user->can('employees.update')
            && $this->withinScope($user, $employee->organization_id, $employee->department_id);
    }
}
