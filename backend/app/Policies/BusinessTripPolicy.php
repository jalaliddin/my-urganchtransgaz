<?php

namespace App\Policies;

use App\Models\BusinessTrip;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationScope;

class BusinessTripPolicy
{
    use ChecksOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->can('business_trips.view');
    }

    public function view(User $user, BusinessTrip $businessTrip): bool
    {
        if ($user->employee?->id === $businessTrip->employee_id) {
            return true;
        }

        return $user->can('business_trips.view')
            && $this->withinScope($user, $businessTrip->employee->organization_id, $businessTrip->employee->department_id);
    }

    /**
     * Creating/updating/cancelling a record directly (independent of any
     * leave request) is HR/admin territory, scoped to the target
     * employee's own organization/department for a non-central creator.
     */
    public function manage(User $user, ?BusinessTrip $businessTrip = null): bool
    {
        if (! $user->can('business_trips.manage')) {
            return false;
        }

        if ($businessTrip === null) {
            return true;
        }

        return $this->withinScope($user, $businessTrip->employee->organization_id, $businessTrip->employee->department_id);
    }
}
