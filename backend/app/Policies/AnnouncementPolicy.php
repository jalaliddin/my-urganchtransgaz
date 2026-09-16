<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

class AnnouncementPolicy
{
    /**
     * Every authenticated user holds `announcements.view`; the controller
     * scopes the query to authored announcements (any status) for
     * `announcements.create` holders, or the live, targeted audience feed
     * for everyone else.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('announcements.view');
    }

    /**
     * The author (or anyone with central/publish reach) can always see
     * their own draft; a plain audience member can only see an
     * announcement once it's actually live and targeted at them.
     */
    public function view(User $user, Announcement $announcement): bool
    {
        if ($user->id === $announcement->author_id || $user->hasCentralAccess() || $user->can('announcements.publish')) {
            return true;
        }

        if (! $user->can('announcements.view') || ! $announcement->isCurrentlyLive()) {
            return false;
        }

        return $user->employee !== null && $announcement->appliesTo($user->employee);
    }

    /**
     * Target-scope restriction (a non-central creator may only target
     * their own organization/department/employees) lives in
     * StoreAnnouncementRequest, the same shape as StoreTaskRequest's
     * per-assignee scope check.
     */
    public function create(User $user): bool
    {
        return $user->can('announcements.create');
    }

    public function update(User $user, Announcement $announcement): bool
    {
        if (! $user->can('announcements.create')) {
            return false;
        }

        return $user->id === $announcement->author_id || $user->hasCentralAccess();
    }

    /**
     * Publishing and archiving are centralized-control actions per §7 —
     * `announcements.publish` is seeded to central-admin only, regardless
     * of who authored the draft.
     */
    public function publish(User $user, Announcement $announcement): bool
    {
        return $user->can('announcements.publish');
    }
}
