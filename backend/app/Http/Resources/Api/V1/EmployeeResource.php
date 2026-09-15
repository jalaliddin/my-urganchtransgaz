<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Deliberately omits passport_number and pinfl: those are encrypted,
 * highly sensitive identifiers that no Phase 1 workflow needs over the API.
 *
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
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
            'user_id' => $this->user_id,
            'organization_id' => $this->organization_id,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'position_id' => $this->position_id,
            'position' => new PositionResource($this->whenLoaded('position')),

            'employee_number' => $this->employee_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'full_name' => $this->fullName(),

            'birth_date' => $this->birth_date,
            'birth_place' => $this->birth_place,
            'gender' => $this->gender,

            'phone' => $this->phone,
            'email' => $this->email,
            'corporate_email' => $this->corporate_email,
            'address' => $this->address,

            'employment_type' => $this->employment_type,
            'hire_date' => $this->hire_date,
            'termination_date' => $this->termination_date,

            'photo' => $this->photo,
            'status' => $this->status,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
