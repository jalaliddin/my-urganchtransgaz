<?php

namespace App\Http\Resources\Api\V1;

use App\Models\KpiTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KpiTemplate
 */
class KpiTemplateResource extends JsonResource
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
            'description' => $this->description,
            'organization_id' => $this->organization_id,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'status' => $this->status,
            'indicators' => KpiIndicatorResource::collection($this->whenLoaded('indicators')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
