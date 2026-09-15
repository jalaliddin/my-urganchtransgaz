<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An employee summary paired with their (nullable) attendance record for
 * today — nullable because an employee who hasn't checked in yet simply
 * has no row for today.
 *
 * @mixin Employee
 */
class TodayAttendanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'employee_id' => $this->id,
            'employee_number' => $this->employee_number,
            'full_name' => $this->fullName(),
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'attendance' => $this->todayAttendance ? new AttendanceRecordResource($this->todayAttendance) : null,
        ];
    }
}
