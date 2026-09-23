<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AttendanceEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendanceEvent
 */
class AttendanceEventResource extends JsonResource
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
            'type' => $this->type,
            // Explicit format, not the bare cast value — see
            // AttendanceRecordResource for why (JSON serialization of a
            // plain Carbon value converts to UTC).
            'occurred_at' => $this->occurred_at?->format('Y-m-d\TH:i:s'),
            'source' => $this->source,
        ];
    }
}
