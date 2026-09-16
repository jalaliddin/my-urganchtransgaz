<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Enums\KpiPeriodType;
use Database\Factories\KpiPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'period_type', 'start_date', 'end_date', 'status'])]
class KpiPeriod extends Model
{
    /** @use HasFactory<KpiPeriodFactory> */
    use HasFactory;

    public function employeeKpis(): HasMany
    {
        return $this->hasMany(EmployeeKpi::class, 'kpi_period_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // "date:Y-m-d", not plain "date" — see Employee::casts() for
            // why a bare "date" cast shifts across the day boundary once
            // JSON-serialized under a non-UTC APP_TIMEZONE.
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'period_type' => KpiPeriodType::class,
            'status' => ActiveStatus::class,
        ];
    }
}
