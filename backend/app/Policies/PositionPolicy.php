<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationScope;

class PositionPolicy
{
    use ChecksOrganizationScope;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('positions.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Position $position): bool
    {
        return $user->can('positions.view')
            && $this->withinScope($user, $position->organization_id);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('positions.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Position $position): bool
    {
        return $user->can('positions.update')
            && $this->withinScope($user, $position->organization_id);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Position $position): bool
    {
        return $user->can('positions.delete')
            && $this->withinScope($user, $position->organization_id);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Position $position): bool
    {
        return $user->can('positions.delete');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Position $position): bool
    {
        return $user->can('positions.delete');
    }
}
