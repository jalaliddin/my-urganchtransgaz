<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TaskActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskActivity
 */
class TaskActivityResource extends JsonResource
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
            'action' => $this->action,
            'description' => $this->description,
            'causer_name' => $this->whenLoaded('causer', fn () => $this->causer?->name),
            'created_at' => $this->created_at,
        ];
    }
}
