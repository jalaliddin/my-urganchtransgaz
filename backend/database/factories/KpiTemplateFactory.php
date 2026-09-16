<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\KpiTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KpiTemplate>
 */
class KpiTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'created_by' => User::factory(),
            'status' => ActiveStatus::Active->value,
        ];
    }
}
