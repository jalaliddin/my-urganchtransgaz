<?php

namespace App\Http\Requests;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('employees.create')) {
            return false;
        }

        return $this->withinScope($user, (int) $this->input('organization_id'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')],

            'employee_number' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_number')],
            'dahua_person_id' => ['nullable', 'string', 'max:64', Rule::unique('employees', 'dahua_person_id')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],

            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', Rule::enum(Gender::class)],

            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'corporate_email' => [
                'required_if:create_account,true', 'nullable', 'email', 'max:255',
                Rule::unique('users', 'email'),
            ],
            'address' => ['nullable', 'string', 'max:500'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'pinfl' => ['nullable', 'digits:14'],

            'employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            'hire_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(EmployeeStatus::class)],

            'create_account' => ['sometimes', 'boolean'],
            'username' => ['required_if:create_account,true', 'nullable', 'string', 'max:255', Rule::unique('users', 'username')],
            'password' => ['required_if:create_account,true', 'nullable', 'string', 'min:8'],
            'role' => ['required_if:create_account,true', 'nullable', Rule::in($this->user()->assignableRoles())],
        ];
    }
}
