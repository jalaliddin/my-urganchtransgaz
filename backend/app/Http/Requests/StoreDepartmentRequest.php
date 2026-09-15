<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('departments.create')) {
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
            'manager_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('departments', 'code')->where('organization_id', $this->input('organization_id')),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::enum(ActiveStatus::class)],
        ];
    }
}
