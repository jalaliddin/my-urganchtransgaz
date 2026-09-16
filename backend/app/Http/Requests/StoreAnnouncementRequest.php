<?php

namespace App\Http\Requests;

use App\Enums\AnnouncementTargetType;
use App\Enums\TaskPriority;
use App\Models\Department;
use App\Models\Employee;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * `announcements.create` gates the action itself; a non-central
     * creator (organization-admin) is additionally restricted to
     * targeting only their own organization, its departments, or its
     * employees — the same per-target scope shape as StoreTaskRequest's
     * per-assignee check. `everyone`/`central`/`role` targets require
     * central reach, since they can broadcast beyond one organization.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('announcements.create')) {
            return false;
        }

        if ($user->hasCentralAccess()) {
            return true;
        }

        foreach ((array) $this->input('targets', []) as $target) {
            $type = $target['target_type'] ?? null;
            $targetId = $target['target_id'] ?? null;

            if (in_array($type, [
                AnnouncementTargetType::Everyone->value,
                AnnouncementTargetType::Central->value,
                AnnouncementTargetType::Role->value,
            ], true)) {
                return false;
            }

            if ($type === AnnouncementTargetType::Organization->value && ! $this->withinScope($user, (int) $targetId)) {
                return false;
            }

            if ($type === AnnouncementTargetType::Department->value) {
                $department = Department::find($targetId);

                if (! $department || ! $this->withinScope($user, $department->organization_id, $department->id)) {
                    return false;
                }
            }

            if ($type === AnnouncementTargetType::Employee->value) {
                $employee = Employee::find($targetId);

                if (! $employee || ! $this->withinScope($user, $employee->organization_id, $employee->department_id)) {
                    return false;
                }
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
            'content' => ['required', 'string'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'publish_at' => ['nullable', 'date'],
            'expire_at' => ['nullable', 'date', 'after:publish_at'],
            'publish_immediately' => ['nullable', 'boolean'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.target_type' => ['required', Rule::enum(AnnouncementTargetType::class)],
            'targets.*.target_id' => ['nullable', 'integer'],
        ];
    }
}
