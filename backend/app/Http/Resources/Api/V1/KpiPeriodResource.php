<?php

namespace App\Http\Resources\Api\V1;

use App\Models\KpiPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KpiPeriod
 */
class KpiPeriodResource extends JsonResource
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
            'period_type' => $this->period_type,
            // Explicit format, not the bare cast value — see
            // EmployeeResource for why a "date" cast still needs this.
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,
        ];
    }
}
