<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKpiTemplateRequest extends FormRequest
{
    use ChecksOrganizationScope;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('kpi.manage')) {
            return false;
        }

        $organizationId = $this->input('organization_id');

        if ($organizationId && ! $this->withinScope($user, (int) $organizationId, $this->input('department_id'))) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'status' => ['nullable', Rule::enum(ActiveStatus::class)],
        ];
    }
}
