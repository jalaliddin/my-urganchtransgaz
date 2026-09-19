<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use App\Models\Employee;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreIssueRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * Anyone with `issues.create` (every role) can report an issue. Roles
     * with company-wide reach may file it against any organization —
     * subordinate or head office; everyone else only against their own,
     * the same per-target scope check `StoreTaskRequest` already uses.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('issues.create') || ! $user->employee) {
            return false;
        }

        return $this->withinScope($user, $this->targetOrganizationId());
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
            'organization_id' => [
                Rule::requiredIf($this->user()->hasCentralAccess()),
                'nullable', 'integer',
                Rule::exists('organizations', 'id')->where('status', ActiveStatus::Active->value),
            ],
            'issue_category_id' => [
                'required', 'integer',
                Rule::exists('issue_categories', 'id')->where('status', ActiveStatus::Active->value),
            ],
            'executor_ids' => ['required', 'array', 'min:1', 'max:30'],
            'executor_ids.*' => ['integer', 'distinct'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Every executor must be a current employee of the chosen organization —
     * checked here rather than with a bare `exists` so a crafted request
     * can't attach someone from an unrelated organization.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ids = array_unique($this->input('executor_ids'));

                $eligible = Employee::issueExecutors()
                    ->where('organization_id', $this->targetOrganizationId())
                    ->whereIn('id', $ids)
                    ->count();

                if ($eligible !== count($ids)) {
                    $validator->errors()->add('executor_ids', 'Ijrochilar tanlangan tashkilotning amaldagi xodimlari bo\'lishi kerak.');
                }
            },
        ];
    }

    public function targetOrganizationId(): int
    {
        return $this->integer('organization_id') ?: (int) $this->user()->employee?->organization_id;
    }
}
