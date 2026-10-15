<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AttendanceDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendanceDevice */
class AttendanceDeviceResource extends JsonResource
{
    /**
     * Never the token: only its hash is stored, and the plain token is
     * returned once, separately, when it's issued.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'name' => $this->name,
            'organization_id' => $this->organization_id,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'is_active' => $this->is_active,
            'last_used_at' => $this->last_used_at,
            'created_at' => $this->created_at,
        ];
    }
}
