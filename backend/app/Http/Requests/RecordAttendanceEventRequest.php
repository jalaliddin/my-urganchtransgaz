<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordAttendanceEventRequest extends FormRequest
{
    /**
     * Authentication is already enforced by the AuthenticateAttendanceDevice
     * middleware ahead of this request; there is no per-user authorization
     * concept for a device.
     */
    public function authorize(): bool
    {
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
            'device_id' => ['required', 'string'],
            'employee_number' => ['required', 'string', Rule::exists('employees', 'employee_number')],
            'event_type' => ['required', Rule::in(['check_in', 'check_out'])],
            'event_time' => ['required', 'date'],
        ];
    }
}
