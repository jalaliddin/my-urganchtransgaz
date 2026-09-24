<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Opens a login for an employee who was added without one — the same
 * account fields StoreEmployeeRequest accepts under its optional
 * `create_account` flag, just required here since creating the account
 * is this endpoint's one job rather than a step tucked inside a larger
 * form.
 */
class StoreEmployeeAccountRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        /** @var Employee $employee */
        $employee = $this->route('employee');

        return $user->can('users.update')
            && $this->withinScope($user, $employee->organization_id, $employee->department_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'corporate_email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in($this->user()->assignableRoles())],
        ];
    }
}
