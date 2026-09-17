<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Issue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Issue
 */
class IssueResource extends JsonResource
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
            'reporter_employee_id' => $this->reporter_employee_id,
            'reporter' => new EmployeeResource($this->whenLoaded('reporter')),
            'organization_id' => $this->organization_id,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),

            'title' => $this->title,
            'description' => $this->description,
            'object_name' => $this->object_name,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,

            'status' => $this->status,
            'resolution_note' => $this->resolution_note,
            'resolved_by' => $this->resolved_by,
            'resolved_by_name' => $this->whenLoaded('resolvedBy', fn () => $this->resolvedBy?->name),
            'resolved_at' => $this->resolved_at,

            'comments' => IssueCommentResource::collection($this->whenLoaded('comments')),
            'activities' => IssueActivityResource::collection($this->whenLoaded('activities')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
