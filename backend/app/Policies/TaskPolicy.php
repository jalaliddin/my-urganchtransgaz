<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationScope;

class TaskPolicy
{
    use ChecksOrganizationScope;

    /**
     * Every authenticated user may list tasks; the controller scopes the
     * query to "created by or assigned to me" for a plain employee and to
     * permitted organizations/departments for everyone else.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        if ($this->isInvolved($user, $task)) {
            return true;
        }

        return $user->can('tasks.view')
            && $this->withinScope($user, $task->organization_id ?? $user->employee?->organization_id, $task->department_id);
    }

    public function create(User $user): bool
    {
        return $user->can('tasks.create');
    }

    /**
     * Editing the task itself (title/description/priority/dates/assignees)
     * and cancelling it are creator/manager actions — never an assignee's,
     * even though an assignee can freely update their own progress.
     *
     * Gated on `tasks.assign`, not `tasks.update`: the base `employee`
     * role also holds `tasks.update` (for its own progress, handled
     * entirely by `updateProgress()` below and never checked here), so
     * `tasks.update` cannot distinguish a manager-tier actor from a plain
     * assignee. Every manager-tier role (organization-admin,
     * department-manager, technical-policy, manager) holds `tasks.assign`
     * and `employee` does not, which is exactly the boundary Module 9
     * draws between "assign/monitor/approve" and "view/update
     * progress/complete."
     */
    public function update(User $user, Task $task): bool
    {
        if ($user->id === $task->creator_id) {
            return true;
        }

        return $user->can('tasks.assign')
            && $this->withinScope($user, $task->organization_id ?? $user->employee?->organization_id, $task->department_id);
    }

    /**
     * Updating one's own progress or marking assigned work complete
     * requires no permission check beyond being currently assigned —
     * mirroring attendance's check-in/check-out precedent, this is an
     * inherent right of the assignment itself, not something gated by
     * role or the `tasks.complete` permission.
     */
    public function updateProgress(User $user, Task $task): bool
    {
        $employeeId = $user->employee?->id;

        return $employeeId !== null && $task->assignees()->where('employees.id', $employeeId)->exists();
    }

    /**
     * Approving completion or reopening a task ("Managers can: approve
     * completion, reopen tasks" — Module 9) is the same actor set as
     * editing the task.
     */
    public function review(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    private function isInvolved(User $user, Task $task): bool
    {
        if ($user->id === $task->creator_id) {
            return true;
        }

        $employeeId = $user->employee?->id;

        return $employeeId !== null && $task->assignees()->where('employees.id', $employeeId)->exists();
    }
}
