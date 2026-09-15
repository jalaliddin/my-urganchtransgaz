<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksOrganizationScope
{
    /**
     * Central-level roles may act on any organization; every other role is
     * confined to the organization (and, for department managers, the
     * department) their own employee record belongs to. This is the backend
     * enforcement boundary for the portal's multi-organization isolation:
     * a user must never reach another organization's records by changing
     * an id, regardless of what the frontend sends.
     */
    protected function withinScope(User $user, int $organizationId, ?int $departmentId = null): bool
    {
        if ($user->hasCentralAccess()) {
            return true;
        }

        $employee = $user->employee;

        if (! $employee || $employee->organization_id !== $organizationId) {
            return false;
        }

        if ($user->hasRole('department-manager') && $departmentId !== null) {
            return $employee->department_id === $departmentId;
        }

        return true;
    }
}
