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
     * `issues.create` gates the action itself. A department-manager may
     * only report an issue for their own organization (their department
     * is filled in from their own record, never from the request);
     * technical-policy and central roles may name any organization — the
     * same per-target scope check `StoreTaskRequest` already uses.
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
        $mustChooseResponsible = $this->user()->mustChooseIssueResponsible();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'object_name' => ['nullable', 'string', 'max:255'],
            'organization_id' => [
                Rule::requiredIf($mustChooseResponsible),
                'nullable', 'integer',
                Rule::exists('organizations', 'id')->where('status', ActiveStatus::Active->value),
            ],
            'issue_category_id' => [
                'required', 'integer',
                Rule::exists('issue_categories', 'id')->where('status', ActiveStatus::Active->value),
            ],
            'responsible_employee_id' => [Rule::requiredIf($mustChooseResponsible), 'nullable', 'integer'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * The responsible employee must belong to the chosen organization and
     * be someone who can actually act on issues — checked here rather than
     * with a bare `exists` so a crafted request can't name an unrelated or
     * unable employee.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->user()->mustChooseIssueResponsible() || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $eligible = Employee::issueHandlers()
                    ->whereKey($this->integer('responsible_employee_id'))
                    ->where('organization_id', $this->targetOrganizationId())
                    ->exists();

                if (! $eligible) {
                    $validator->errors()->add('responsible_employee_id', 'Tanlangan xodim bu tashkilotda mas\'ul bo\'la olmaydi.');
                }
            },
        ];
    }

    public function targetOrganizationId(): int
    {
        return $this->integer('organization_id') ?: (int) $this->user()->employee?->organization_id;
    }
}
