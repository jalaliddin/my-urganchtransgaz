<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Enums\KpiPeriodType;
use App\Models\KpiPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KpiPeriod>
 */
class KpiPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // A unique month offset, not a narrow random date range — several
        // periods are often created within the same test run, and a
        // narrow range risks colliding with the (period_type, start_date)
        // unique constraint.
        $start = now()->subMonths(fake()->unique()->numberBetween(0, 10_000))->startOfMonth();

        return [
            'name' => $start->format('Y-m'),
            'period_type' => KpiPeriodType::Monthly->value,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $start->copy()->endOfMonth()->format('Y-m-d'),
            'status' => ActiveStatus::Active->value,
        ];
    }
}
