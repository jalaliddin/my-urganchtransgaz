<?php

namespace App\Policies;

use App\Models\KpiPeriod;
use App\Models\User;

class KpiPeriodPolicy
{
    /**
     * Periods are company-wide (no organization/department scope of
     * their own), so every check here is a plain permission check.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('kpi.view');
    }

    public function create(User $user): bool
    {
        return $user->can('kpi.manage');
    }

    public function update(User $user, KpiPeriod $period): bool
    {
        return $user->can('kpi.manage');
    }
}
