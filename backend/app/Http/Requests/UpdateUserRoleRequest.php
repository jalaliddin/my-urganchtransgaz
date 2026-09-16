<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    /**
     * Same role list StoreEmployeeRequest already restricts a scoped
     * creator to — granting super-admin/central-admin/organization-admin
     * stays a central-admin-only privilege, whether at account creation
     * or a later role change.
     *
     * @var string[]
     */
    private array $assignableRolesForScopedCreators = [
        'department-manager', 'hr', 'safety-manager', 'technical-policy', 'manager', 'employee',
    ];

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
            'role' => ['required', Rule::in($assignableRoles)],
        ];
    }
}
