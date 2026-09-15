<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $department = $this->route('department');

        if (! $user->can('departments.update')) {
            return false;
        }

        return $this->withinScope($user, $department->organization_id, $department->id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $department = $this->route('department');
        $organizationId = $this->input('organization_id', $department->organization_id);

        return [
            'organization_id' => ['sometimes', 'required', 'integer', Rule::exists('organizations', 'id')],
            'manager_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('departments', 'code')->where('organization_id', $organizationId)->ignore($department->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::enum(ActiveStatus::class)],
        ];
    }
}
