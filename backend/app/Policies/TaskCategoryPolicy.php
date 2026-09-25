<?php

namespace App\Policies;

use App\Models\TaskCategory;
use App\Models\User;

/**
 * One permission covers the whole CRUD, like IssueCategoryPolicy: the
 * category list is company-wide reference data. Reading the active ones for
 * the task form goes through `GET /tasks/options` instead, open to everyone.
 */
class TaskCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('task_categories.manage');
    }

    public function view(User $user, TaskCategory $taskCategory): bool
    {
        return $user->can('task_categories.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('task_categories.manage');
    }

    public function update(User $user, TaskCategory $taskCategory): bool
    {
        return $user->can('task_categories.manage');
    }

    public function delete(User $user, TaskCategory $taskCategory): bool
    {
        return $user->can('task_categories.manage');
    }
}
