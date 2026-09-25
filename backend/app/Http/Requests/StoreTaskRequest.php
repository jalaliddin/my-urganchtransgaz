<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use App\Enums\TaskPriority;
use App\Models\Employee;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * `tasks.create` gates the action itself; every individual assignee is
     * additionally checked against the creator's own scope, the same
     * per-target shape as StoreEmployeeDocumentRequest — a manager cannot
     * assign a task to someone outside their organization/department just
     * because they hold the `tasks.create` permission.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('tasks.create')) {
            return false;
        }

        $organizationId = $this->input('organization_id');

        if ($organizationId && ! $this->withinScope($user, (int) $organizationId, $this->input('department_id'))) {
            return false;
        }

        foreach ((array) $this->input('assignee_ids', []) as $employeeId) {
            $employee = Employee::find($employeeId);

            if (! $employee || ! $this->withinScope($user, $employee->organization_id, $employee->department_id)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'task_category_id' => [
                'nullable', 'integer',
                Rule::exists('task_categories', 'id')->where('status', ActiveStatus::Active->value),
            ],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'assignee_ids' => ['required', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', Rule::exists('employees', 'id')],
        ];
    }
}
