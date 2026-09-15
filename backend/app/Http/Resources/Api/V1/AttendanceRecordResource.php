<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendanceRecord
 */
class AttendanceRecordResource extends JsonResource
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
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            // Explicit format, not the bare cast value — see EmployeeResource
            // for why a "date" cast still needs this. check_in/check_out
            // are full datetimes, not dates, but the same bug applies: a
            // plain Carbon value's JSON serialization converts to UTC,
            // which would display e.g. a 09:00 Tashkent check-in as 04:00.
            'date' => $this->date?->format('Y-m-d'),
            'check_in' => $this->check_in?->format('Y-m-d\TH:i:s'),
            'check_out' => $this->check_out?->format('Y-m-d\TH:i:s'),
            'worked_minutes' => $this->worked_minutes,
            'status' => $this->status,
            'source' => $this->source,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
