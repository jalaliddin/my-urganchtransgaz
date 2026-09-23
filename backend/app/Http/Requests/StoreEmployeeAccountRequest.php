<?php

namespace App\Http\Requests;

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
    /**
     * Same role list StoreEmployeeRequest/UpdateUserRoleRequest already
     * restrict a scoped creator to.
     *
     * @var string[]
     */
    private array $assignableRolesForScopedCreators = [
        'department-manager', 'hr', 'safety-manager', 'technical-policy', 'manager', 'employee',
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('users.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assignableRoles = $this->user()->hasRole('central-admin')
            ? array_merge($this->assignableRolesForScopedCreators, ['organization-admin', 'central-admin', 'super-admin'])
            : $this->assignableRolesForScopedCreators;

        return [
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'corporate_email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in($assignableRoles)],
        ];
    }
}
