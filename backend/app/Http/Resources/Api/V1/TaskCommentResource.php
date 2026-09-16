<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskComment
 */
class TaskCommentResource extends JsonResource
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
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'body' => $this->body,
            'created_at' => $this->created_at,
        ];
    }
}
