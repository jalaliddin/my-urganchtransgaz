<?php

namespace App\Policies;

use App\Models\KpiTemplate;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationScope;

class KpiTemplatePolicy
{
    use ChecksOrganizationScope;

    /**
     * Every authenticated user with `kpi.view` may list templates
     * (needed to show indicator/context names alongside their own
     * results); the controller scopes the query the same way every
     * other module does.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('kpi.view');
    }

    public function view(User $user, KpiTemplate $template): bool
    {
        return $user->can('kpi.view')
            && $this->withinScope($user, $template->organization_id ?? $user->employee?->organization_id, $template->department_id);
    }

    /**
     * Creating/updating a template and authoring its indicators —
     * `kpi.manage` only (organization-admin or central).
     */
    public function manage(User $user, ?KpiTemplate $template = null): bool
    {
        if (! $user->can('kpi.manage')) {
            return false;
        }

        if ($template === null) {
            return true;
        }

        return $this->withinScope($user, $template->organization_id ?? $user->employee?->organization_id, $template->department_id);
    }
}
