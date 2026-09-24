<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationScope;

class IssuePolicy
{
    use ChecksOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->can('issues.view');
    }

    /**
     * The reporter, an executor, Technical Policy Service (the resolving
     * authority, company-wide) and central leadership can always open a
     * single issue directly. A department-manager also sees what their
     * department raised or is executing, and an organization-admin their
     * whole organization's issues — the scopes `IssueController::index()`
     * applies to the list, kept identical here so a direct link can't reach
     * further than the list does.
     */
    public function view(User $user, Issue $issue): bool
    {
        if ($this->isLeadership($user)) {
            return true;
        }

        if ($user->id === $issue->reporter?->user_id) {
            return true;
        }

        if ($issue->executors()->where('employees.user_id', $user->id)->exists()) {
            return true;
        }

        if (! $user->can('issues.view') || ! $user->employee) {
            return false;
        }

        $employee = $user->employee;

        if ($user->hasRole('organization-admin') && $issue->organization_id === $employee->organization_id) {
            return true;
        }

        return $user->hasRole('department-manager')
            && $employee->department_id !== null
            && ($issue->department_id === $employee->department_id
                || $issue->executors()->where('employees.department_id', $employee->department_id)->exists());
    }

    public function create(User $user): bool
    {
        return $user->can('issues.create');
    }

    /**
     * "Only Technical Policy Service's own response, given when they mark
     * it fixed, counts as resolution" — deliberately narrower than every
     * other role that can merely view or report issues. Checked through
     * the `issues.resolve` permission (seeded only to technical-policy,
     * plus central-admin's '*') rather than the role name, so the grant is
     * the real source of truth: Gate::before only bypasses super-admin,
     * and a role-name check here had left central-admin shown a Resolve
     * button (it holds the permission) that then answered 403.
     */
    public function resolve(User $user, Issue $issue): bool
    {
        return $user->can('issues.resolve');
    }

    /**
     * Central leadership sees every issue on the map/list regardless of
     * department — deliberately not the broader `hasCentralAccess()` set
     * (which also includes hr and safety-manager): the user asked
     * specifically for Technical Policy Service and central-admin/
     * super-admin, not every centrally-scoped role.
     */
    private function isLeadership(User $user): bool
    {
        return $user->hasRole('technical-policy')
            || $user->hasRole('central-admin')
            || $user->hasRole('super-admin');
    }
}
