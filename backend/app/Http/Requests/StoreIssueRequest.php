<?php

namespace App\Http\Requests;

use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * `issues.create` gates the action itself; a department-manager may
     * only report an issue against their own department (technical-policy
     * has no such restriction — they can flag a problem anywhere), the
     * same per-target scope check `StoreTaskRequest` already uses.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('issues.create')) {
            return false;
        }

        $organizationId = $this->integer('organization_id') ?: $user->employee?->organization_id;
        $departmentId = $this->integer('department_id') ?: $user->employee?->department_id;

        if (! $organizationId) {
            return false;
        }

        return $this->withinScope($user, $organizationId, $departmentId);
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
            'description' => ['nullable', 'string', 'max:2000'],
            'object_name' => ['nullable', 'string', 'max:255'],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
