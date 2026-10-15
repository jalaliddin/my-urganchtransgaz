<?php

namespace App\Http\Requests;

use App\Models\AttendanceDevice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceDeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', AttendanceDevice::class);
    }

    /**
     * `device_id` is what the terminal's client sends with every event
     * (e.g. TURNSTILE-1), so it's kept to a plain identifier.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => [
                'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('attendance_devices', 'device_id'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
        ];
    }
}
