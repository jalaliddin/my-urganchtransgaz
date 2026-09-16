<?php

namespace App\Policies;

use App\Models\EmployeeKpi;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationScope;

class EmployeeKpiPolicy
{
    use ChecksOrganizationScope;

    public function viewAny(User $user): bool
    {
        return $user->can('kpi.view');
    }

    /**
     * A `kpi.manage` holder in scope sees a record always (draft or
     * approved) — they're the one entering/approving it. The record's
     * own employee sees it once published. A *supervisory* role
     * (manager/department-manager/organization-admin/central) in scope
     * also sees it once published — but a bare `employee` role does not,
     * even for a coworker in the same organization: unlike documents/
     * attendance/tasks, Module 11 explicitly narrows this one to
     * "Employees should see their own KPI results," not their org's.
     */
    public function view(User $user, EmployeeKpi $employeeKpi): bool
    {
        $inScope = $this->withinScope($user, $employeeKpi->employee->organization_id, $employeeKpi->employee->department_id);

        if ($user->can('kpi.manage') && $inScope) {
            return true;
        }

        if ($employeeKpi->approved_at === null) {
            return false;
        }

        if ($user->employee?->id === $employeeKpi->employee_id) {
            return true;
        }

        return $this->isSupervisor($user) && $user->can('kpi.view') && $inScope;
    }

    /**
     * Manager/department-manager/organization-admin (or central) — the
     * roles Module 11 grants oversight of "permitted employees" to.
     * `organization-admin` is included because it already implies
     * `kpi.manage`, which the `manage()` branch above covers first; this
     * only matters if that ever changes.
     */
    private function isSupervisor(User $user): bool
    {
        return $user->hasAnyRole(['manager', 'department-manager', 'organization-admin']) || $user->hasCentralAccess();
    }

    /**
     * Entering/correcting target_value/actual_value/comment for a given
     * employee — `kpi.manage` plus scope against that employee's own
     * organization/department.
     */
    public function manage(User $user, EmployeeKpi $employeeKpi): bool
    {
        return $user->can('kpi.manage')
            && $this->withinScope($user, $employeeKpi->employee->organization_id, $employeeKpi->employee->department_id);
    }

    public function approve(User $user, EmployeeKpi $employeeKpi): bool
    {
        return $this->manage($user, $employeeKpi);
    }
}
