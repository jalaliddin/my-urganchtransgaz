<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * Nobody changes their own role (claude.md §60), and nobody changes
     * the role of an account more privileged than one they could create.
     * An employee with no account falls through to the controller's 404.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        /** @var Employee $employee */
        $employee = $this->route('employee');

        if (! $user->can('users.update') || ! $this->withinScope($user, $employee->organization_id, $employee->department_id)) {
            return false;
        }

        return ! $employee->user
            || ($employee->user_id !== $user->id && $user->canManageAccountOf($employee->user));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in($this->user()->assignableRoles())],
        ];
    }
}
