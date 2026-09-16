<?php

namespace App\Http\Resources\Api\V1;

use App\Models\EmployeeKpi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeKpi
 */
class EmployeeKpiResource extends JsonResource
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
            'kpi_period_id' => $this->kpi_period_id,
            'period' => new KpiPeriodResource($this->whenLoaded('period')),
            'kpi_indicator_id' => $this->kpi_indicator_id,
            'indicator' => new KpiIndicatorResource($this->whenLoaded('indicator')),

            'target_value' => (float) $this->target_value,
            'actual_value' => $this->actual_value === null ? null : (float) $this->actual_value,
            'score' => $this->score === null ? null : (float) $this->score,
            'weight' => $this->weight,
            'weighted_score' => $this->weighted_score === null ? null : (float) $this->weighted_score,
            'comment' => $this->comment,

            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
