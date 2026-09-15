<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->employee !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employee = $this->user()->employee;

        return [
            // Direct-apply fields.
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],

            // Approval-gated fields — validated now so the employee gets
            // immediate feedback, even though nothing is applied until
            // HR/admin approves the resulting change request.
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'birth_place' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', Rule::enum(Gender::class)],
            'passport_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'pinfl' => ['sometimes', 'nullable', 'digits:14'],
            'organization_id' => ['sometimes', 'required', 'integer', Rule::exists('organizations', 'id')],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('departments', 'id')],
            'position_id' => ['sometimes', 'nullable', 'integer', Rule::exists('positions', 'id')],
            'employee_number' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('employees', 'employee_number')->ignore($employee->id),
            ],
            'hire_date' => ['sometimes', 'nullable', 'date'],

            // Emergency contacts / bank information — fully self-managed.
            'contacts' => ['sometimes', 'array'],
            'contacts.*.id' => ['nullable', 'integer', Rule::exists('employee_contacts', 'id')->where('employee_id', $employee->id)],
            'contacts.*.type' => ['required_with:contacts', Rule::in(['emergency', 'bank'])],
            'contacts.*.full_name' => ['required_with:contacts', 'string', 'max:255'],
            'contacts.*.relationship' => ['nullable', 'string', 'max:100'],
            'contacts.*.phone' => ['nullable', 'string', 'max:30'],
            'contacts.*.address' => ['nullable', 'string', 'max:500'],
            'contacts.*.bank_name' => ['nullable', 'string', 'max:255'],
            'contacts.*.bank_account_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
