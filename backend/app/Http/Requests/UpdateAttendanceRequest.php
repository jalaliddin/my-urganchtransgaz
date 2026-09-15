<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    use ChecksOrganizationScope;

    public function authorize(): bool
    {
        $user = $this->user();
        $record = $this->route('attendanceRecord');

        if (! $user->can('attendance.manage')) {
            return false;
        }

        return $this->withinScope($user, $record->employee->organization_id, $record->employee->department_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
