<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use App\Enums\TaskPriority;
use App\Models\Employee;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * Gated on `tasks.assign`, not `tasks.update` — see TaskPolicy::update()
     * for why `tasks.update` (also held by the base `employee` role) can't
     * distinguish a manager-tier actor from a plain assignee.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $task = $this->route('task');

        $isCreatorOrManager = $user->id === $task->creator_id
            || ($user->can('tasks.assign') && $this->withinScope($user, $task->organization_id ?? $user->employee?->organization_id, $task->department_id));

        if (! $isCreatorOrManager) {
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
     * A task may keep the category it already has even after that category
     * was deactivated; switching to a different one requires an active one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $currentCategoryId = $this->route('task')->task_category_id;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'task_category_id' => [
                'nullable', 'integer',
                Rule::exists('task_categories', 'id')->where(fn ($query) => $query->where(
                    fn ($activeOrCurrent) => $activeOrCurrent->where('status', ActiveStatus::Active->value)
                        ->orWhere('id', $currentCategoryId)
                )),
            ],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'assignee_ids' => ['sometimes', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', Rule::exists('employees', 'id')],
        ];
    }
}
