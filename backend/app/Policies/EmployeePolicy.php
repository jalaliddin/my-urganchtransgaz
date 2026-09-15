<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationScope;

class EmployeePolicy
{
    use ChecksOrganizationScope;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('employees.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Employee $employee): bool
    {
        if ($user->employee?->id === $employee->id) {
            return true;
        }

        return $user->can('employees.view')
            && $this->withinScope($user, $employee->organization_id, $employee->department_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('employees.create');
    }

    /**
     * Determine whether the user can update the model.
     *
     * Self-service editing (with restricted, approval-routed fields) is a
     * later phase; Phase 1's update endpoint is admin/HR-facing only.
     */
    public function update(User $user, Employee $employee): bool
    {
        return $user->can('employees.update')
            && $this->withinScope($user, $employee->organization_id, $employee->department_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('employees.delete')
            && $this->withinScope($user, $employee->organization_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Employee $employee): bool
    {
        return $user->can('employees.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Employee $employee): bool
    {
        return $user->can('employees.delete');
    }
}
