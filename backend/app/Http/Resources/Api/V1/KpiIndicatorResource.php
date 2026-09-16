<?php

namespace App\Http\Resources\Api\V1;

use App\Models\KpiIndicator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin KpiIndicator
 */
class KpiIndicatorResource extends JsonResource
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
            'kpi_template_id' => $this->kpi_template_id,
            'name' => $this->name,
            'description' => $this->description,
            'weight' => $this->weight,
            'target' => (float) $this->target,
            'measurement_unit' => $this->measurement_unit,
            'calculation_type' => $this->calculation_type,
            'period' => $this->period,
            'status' => $this->status,
        ];
    }
}
