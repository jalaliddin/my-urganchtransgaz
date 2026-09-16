<?php

namespace App\Policies;

use App\Models\User;

class SettingPolicy
{
    /**
     * Settings has no read-only audience distinct from who can change
     * them — §7's "administrators must have centralized control...
     * over... system settings" — so one permission gates both.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function manage(User $user): bool
    {
        return $user->can('settings.manage');
    }
}
