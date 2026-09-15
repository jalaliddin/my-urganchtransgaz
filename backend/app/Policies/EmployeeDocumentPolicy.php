<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Policies\Concerns\ChecksOrganizationScope;

class EmployeeDocumentPolicy
{
    use ChecksOrganizationScope;

    /**
     * Every authenticated user may list documents; the controller scopes
     * the query to "own documents" for a plain employee and to permitted
     * organizations/departments for everyone else.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EmployeeDocument $document): bool
    {
        if ($user->employee?->id === $document->employee_id) {
            return true;
        }

        return $user->can('documents.view')
            && $this->withinScope($user, $document->employee->organization_id, $document->employee->department_id);
    }

    /**
     * Uploading for oneself is always allowed; uploading on someone else's
     * behalf (an explicit employee_id in the request) is gated in
     * StoreEmployeeDocumentRequest, which knows the target employee.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * An employee may delete their own document only while it is still
     * pending review; once approved or rejected it is part of the record.
     */
    public function delete(User $user, EmployeeDocument $document): bool
    {
        if ($user->employee?->id === $document->employee_id) {
            return $document->status === DocumentStatus::Pending;
        }

        return $user->can('documents.delete')
            && $this->withinScope($user, $document->employee->organization_id, $document->employee->department_id);
    }

    /**
     * Determine whether the user can approve or reject the document.
     */
    public function review(User $user, EmployeeDocument $document): bool
    {
        return $user->can('documents.approve')
            && $this->withinScope($user, $document->employee->organization_id, $document->employee->department_id);
    }
}
