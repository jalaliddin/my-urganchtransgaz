<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessTripRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * `business_trips.manage` gates the action itself; the target
     * employee must additionally fall within the creator's own scope —
     * the same shape as StoreEmployeeDocumentRequest's on-behalf-of check.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('business_trips.manage')) {
            return false;
        }

        $employee = Employee::find($this->input('employee_id'));

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
            'destination' => ['required', 'string', 'max:255'],
            'purpose' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'order_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
