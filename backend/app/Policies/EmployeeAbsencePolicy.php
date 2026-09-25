<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationScope;

class EmployeeAbsencePolicy
{
    use ChecksOrganizationScope;

    /**
     * Every authenticated user may list absences; the controller narrows
     * a plain employee to their own and everyone else to the
     * organizations/departments they're scoped to.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EmployeeAbsence $absence): bool
    {
        return $this->canSeeEmployee($user, $absence->employee);
    }

    /**
     * Recording an absence for a specific employee is checked against
     * that employee in StoreEmployeeAbsenceRequest.
     */
    public function create(User $user): bool
    {
        return $user->can('absences.manage');
    }

    /**
     * A cancelled record is history; it can't be edited back to life.
     */
    public function update(User $user, EmployeeAbsence $absence): bool
    {
        return ! $absence->isCancelled() && $this->canManageEmployee($user, $absence->employee);
    }

    /**
     * Determine whether the user can cancel the model.
     */
    public function cancel(User $user, EmployeeAbsence $absence): bool
    {
        return $this->update($user, $absence);
    }

    public function canSeeEmployee(User $user, Employee $employee): bool
    {
        if ($user->employee?->id === $employee->id) {
            return true;
        }

        return $user->can('absences.view')
            && $this->withinScope($user, $employee->organization_id, $employee->department_id);
    }

    public function canManageEmployee(User $user, Employee $employee): bool
    {
        return $user->can('absences.manage')
            && $this->withinScope($user, $employee->organization_id, $employee->department_id);
    }
}
