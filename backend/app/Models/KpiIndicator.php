<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Enums\KpiCalculationType;
use App\Enums\KpiPeriodType;
use Database\Factories\KpiIndicatorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'kpi_template_id', 'name', 'description', 'weight', 'target',
    'measurement_unit', 'calculation_type', 'period', 'status',
])]
class KpiIndicator extends Model
{
    /** @use HasFactory<KpiIndicatorFactory> */
    use HasFactory;

    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }

    public function employeeKpis(): HasMany
    {
        return $this->hasMany(EmployeeKpi::class, 'kpi_indicator_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'target' => 'decimal:2',
            'calculation_type' => KpiCalculationType::class,
            'period' => KpiPeriodType::class,
            'status' => ActiveStatus::class,
        ];
    }
}
