<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'creator_id' => $this->creator_id,
            'creator_name' => $this->whenLoaded('creator', fn () => $this->creator->name),
            'organization_id' => $this->organization_id,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'task_category_id' => $this->task_category_id,
            'category' => new TaskCategoryResource($this->whenLoaded('category')),

            'assignees' => EmployeeResource::collection($this->whenLoaded('assignees')),

            'priority' => $this->priority,
            'status' => $this->status,

            // Explicit format, not the bare cast value — see EmployeeResource
            // for why a "date" cast still needs this.
            'start_date' => $this->start_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'completed_at' => $this->completed_at,

            'progress' => $this->progress,
            'result' => $this->result,

            'comments' => TaskCommentResource::collection($this->whenLoaded('comments')),
            'activities' => TaskActivityResource::collection($this->whenLoaded('activities')),
            'attachments' => TaskAttachmentResource::collection($this->whenLoaded('attachments')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
