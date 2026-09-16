<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeKpi;
use App\Models\KpiIndicator;
use App\Models\KpiPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeKpi>
 */
class EmployeeKpiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'kpi_period_id' => KpiPeriod::factory(),
            'kpi_indicator_id' => KpiIndicator::factory(),
            'target_value' => 100,
            'weight' => 50,
        ];
    }

    public function approved(float $actualValue = 100, float $score = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'actual_value' => $actualValue,
            'score' => $score,
            'weighted_score' => round($score * ($attributes['weight'] ?? 50) / 100, 2),
            'approved_at' => now(),
        ]);
    }
}
