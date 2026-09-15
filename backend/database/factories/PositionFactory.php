<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\Organization;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'title' => fake()->unique()->jobTitle(),
            'code' => fake()->unique()->bothify('POS-###'),
            'status' => ActiveStatus::Active->value,
        ];
    }
}
