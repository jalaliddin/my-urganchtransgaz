<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Enums\KpiCalculationType;
use App\Enums\KpiPeriodType;
use App\Models\KpiIndicator;
use App\Models\KpiTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KpiIndicator>
 */
class KpiIndicatorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kpi_template_id' => KpiTemplate::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'weight' => 50,
            'target' => 100,
            'measurement_unit' => '%',
            'calculation_type' => KpiCalculationType::Percentage->value,
            'period' => KpiPeriodType::Monthly->value,
            'status' => ActiveStatus::Active->value,
        ];
    }
}
