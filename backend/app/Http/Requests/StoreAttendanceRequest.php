<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Models\Employee;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * A manual attendance entry is always a correction made on someone's
     * behalf (self-service uses the check-in/check-out endpoints instead),
     * so this always requires `attendance.manage` plus scope.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user->can('attendance.manage')) {
            return false;
        }

        $employee = Employee::find($this->integer('employee_id'));

        return $employee && $this->withinScope($user, $employee->organization_id, $employee->department_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
            'date' => [
                'required', 'date', 'before_or_equal:today',
                Rule::unique('attendance_records', 'date')->where('employee_id', $this->integer('employee_id')),
            ],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
