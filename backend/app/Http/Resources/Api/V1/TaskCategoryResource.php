<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TaskCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskCategory */
class TaskCategoryResource extends JsonResource
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
            'name' => $this->name,
            'code' => $this->code,
            'color' => $this->color,
            'sort_order' => $this->sort_order,
            'status' => $this->status,
            'tasks_count' => $this->whenCounted('tasks'),
        ];
    }
}
