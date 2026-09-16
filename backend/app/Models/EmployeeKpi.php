<?php

namespace App\Models;

use Database\Factories\EmployeeKpiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id', 'kpi_period_id', 'kpi_indicator_id', 'target_value',
    'actual_value', 'score', 'weight', 'weighted_score', 'comment',
    'approved_by', 'approved_at',
])]
class EmployeeKpi extends Model
{
    /** @use HasFactory<EmployeeKpiFactory> */
    use HasFactory;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(KpiPeriod::class, 'kpi_period_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(KpiIndicator::class, 'kpi_indicator_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:2',
            'actual_value' => 'decimal:2',
            'score' => 'decimal:2',
            'weight' => 'integer',
            'weighted_score' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }
}
