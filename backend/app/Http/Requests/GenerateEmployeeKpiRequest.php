<?php

namespace App\Http\Requests;

use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateEmployeeKpiRequest extends FormRequest
{
    use ChecksOrganizationScope;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('kpi.manage')) {
            return false;
        }

        $organizationId = $this->input('organization_id');

        if ($organizationId && ! $this->withinScope($user, (int) $organizationId, $this->input('department_id'))) {
            return false;
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
            'kpi_period_id' => ['required', 'integer', Rule::exists('kpi_periods', 'id')],
            'kpi_template_id' => ['required', 'integer', Rule::exists('kpi_templates', 'id')],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ];
    }
}
