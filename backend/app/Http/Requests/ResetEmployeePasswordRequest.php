<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An admin-initiated reset (setting a new password for someone else's
 * account) — unlike ChangePasswordRequest (self-service), there is no
 * `current_password` to confirm, which is exactly why it must be confined
 * to accounts within the actor's own scope and no more privileged than
 * one they could create: otherwise a reset is a takeover.
 */
class ResetEmployeePasswordRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * Determine if the user is authorized to make this request. An
     * employee with no account falls through to the controller's 404.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        /** @var Employee $employee */
        $employee = $this->route('employee');

        return $user->can('users.update')
            && $this->withinScope($user, $employee->organization_id, $employee->department_id)
            && (! $employee->user || $user->canManageAccountOf($employee->user));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
