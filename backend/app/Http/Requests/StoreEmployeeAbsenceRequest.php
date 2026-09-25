<?php

namespace App\Http\Requests;

use App\Enums\AbsenceType;
use App\Models\Employee;
use App\Models\Setting;
use App\Policies\EmployeeAbsencePolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeAbsenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(EmployeeAbsencePolicy $policy): bool
    {
        if (! $this->user()->can('absences.manage')) {
            return false;
        }

        $employee = Employee::find($this->integer('employee_id'));

        // A missing employee is a validation error, not a 403.
        return $employee === null || $policy->canManageEmployee($this->user(), $employee);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            ...self::absenceRules($this->input('type')),
        ];
    }

    /**
     * Shared with UpdateEmployeeAbsenceRequest.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function absenceRules(mixed $type): array
    {
        return [
            'type' => ['required', Rule::enum(AbsenceType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'document_date' => ['nullable', 'date'],
            'destination' => [
                Rule::requiredIf($type === AbsenceType::BusinessTrip->value),
                'nullable', 'string', 'max:255',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => [
                'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png',
                'max:'.Setting::get('documents.max_upload_kb', 10240),
            ],
        ];
    }
}
