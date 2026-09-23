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
     * A device resolves the employee by whichever id it actually has: a
     * biometric device that was set up with the internal employee_number
     * sends that, while the Dahua bridge only knows the person id the
     * terminal itself was enrolled with, so it sends dahua_person_id
     * instead. Exactly one of the two is required — never both, and never
     * neither, so the controller always has a single unambiguous id to
     * resolve.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string'],
            'employee_number' => [
                'required_without:dahua_person_id', 'prohibits:dahua_person_id', 'string',
                Rule::exists('employees', 'employee_number'),
            ],
            'dahua_person_id' => [
                'required_without:employee_number', 'string',
                Rule::exists('employees', 'dahua_person_id'),
            ],
            'event_type' => ['required', Rule::in(['check_in', 'check_out'])],
            'event_time' => ['required', 'date'],
        ];
    }
}
