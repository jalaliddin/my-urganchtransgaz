<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceDeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('attendance_device'));
    }

    /**
     * `device_id` can't change: every client of the device sends it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'organization_id' => ['sometimes', 'nullable', 'integer', Rule::exists('organizations', 'id')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
