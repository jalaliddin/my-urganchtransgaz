<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use App\Enums\OrganizationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('organizations.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = $this->route('organization');

        return [
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('organizations', 'id'),
                Rule::notIn([$organization->id]),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:50'],
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('organizations', 'code')->ignore($organization->id)],
            'type' => ['sometimes', 'required', Rule::enum(OrganizationType::class)],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'director_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ActiveStatus::class)],
        ];
    }
}
