<?php

namespace App\Policies;

use App\Models\IssueCategory;
use App\Models\User;

/**
 * One permission covers the whole CRUD: managing the category list is an
 * editorial job for whoever runs issue handling (Technical Policy Service),
 * not something each action needs to be granted separately.
 */
class IssueCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('issue_categories.manage');
    }

    public function view(User $user, IssueCategory $issueCategory): bool
    {
        return $user->can('issue_categories.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('issue_categories.manage');
    }

    public function update(User $user, IssueCategory $issueCategory): bool
    {
        return $user->can('issue_categories.manage');
    }

    public function delete(User $user, IssueCategory $issueCategory): bool
    {
        return $user->can('issue_categories.manage');
    }
}
