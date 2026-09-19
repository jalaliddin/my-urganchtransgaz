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
     * The reporter, the responsible employee, Technical Policy Service (the resolving authority,
     * company-wide), and central leadership can always open a single
     * issue directly; a department-manager additionally needs it to be
     * within their own department, the same `withinScope()` boundary
     * every other module in this app enforces.
     */
    public function view(User $user, Issue $issue): bool
    {
        if ($user->id === $issue->reporter?->user_id || $user->id === $issue->responsible?->user_id) {
            return true;
        }

        if ($this->isLeadership($user)) {
            return true;
        }

        return $user->can('issues.view') && $this->withinScope($user, $issue->organization_id, $issue->department_id);
    }

    public function create(User $user): bool
    {
        return $user->can('issues.create');
    }

    /**
     * "Only Technical Policy Service's own response, given when they mark
     * it fixed, counts as resolution" — deliberately narrower than every
     * other role that can merely view or report issues, including
     * central-admin's own reach elsewhere in this app (central-admin/
     * super-admin still resolve issues through the universal Gate::before
     * bypass, the same as every other "only role X" rule in this
     * codebase — this policy method is never consulted for them).
     */
    public function resolve(User $user, Issue $issue): bool
    {
        return $user->hasRole('technical-policy');
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
