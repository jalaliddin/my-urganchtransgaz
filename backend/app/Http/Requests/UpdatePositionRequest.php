<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePositionRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $position = $this->route('position');

        if (! $user->can('positions.update')) {
            return false;
        }

        return $this->withinScope($user, $position->organization_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $position = $this->route('position');
        $organizationId = $this->input('organization_id', $position->organization_id);

        return [
            'organization_id' => ['sometimes', 'required', 'integer', Rule::exists('organizations', 'id')],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('positions', 'code')->where('organization_id', $organizationId)->ignore($position->id),
            ],
            'status' => ['nullable', Rule::enum(ActiveStatus::class)],
        ];
    }
}
