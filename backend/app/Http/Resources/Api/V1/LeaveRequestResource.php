<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeaveRequest
 */
class LeaveRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'type' => $this->type,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'reason' => $this->reason,
            'status' => $this->status,

            'department_reviewed_by' => $this->department_reviewed_by,
            'department_reviewed_at' => $this->department_reviewed_at,
            'hr_reviewed_by' => $this->hr_reviewed_by,
            'hr_reviewed_at' => $this->hr_reviewed_at,
            'rejection_reason' => $this->rejection_reason,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
