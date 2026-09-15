<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Department */
class DepartmentResource extends JsonResource
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
            'organization_id' => $this->organization_id,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'manager_id' => $this->manager_id,
            'manager' => new EmployeeResource($this->whenLoaded('manager')),
            'name' => $this->name,
            'short_name' => $this->short_name,
            'code' => $this->code,
            'description' => $this->description,
            'status' => $this->status,
            'employees_count' => $this->whenCounted('employees'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
