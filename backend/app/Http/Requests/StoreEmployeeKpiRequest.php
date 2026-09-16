<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeKpiRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * `kpi.manage` plus scope against the target employee, the same
     * per-target shape as StoreEmployeeDocumentRequest/StoreTaskRequest.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('kpi.manage')) {
            return false;
        }

        $employee = Employee::find($this->integer('employee_id'));

        return $employee && $this->withinScope($user, $employee->organization_id, $employee->department_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'kpi_period_id' => ['required', 'integer', Rule::exists('kpi_periods', 'id')],
            'kpi_indicator_id' => [
                'required', 'integer', Rule::exists('kpi_indicators', 'id'),
                Rule::unique('employee_kpis')->where(fn ($query) => $query
                    ->where('employee_id', $this->input('employee_id'))
                    ->where('kpi_period_id', $this->input('kpi_period_id'))),
            ],
            'target_value' => ['nullable', 'numeric', 'min:0'],
            'actual_value' => ['nullable', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
