<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\Department;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
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
            'manager_id' => null,
            'name' => fake()->unique()->jobTitle().' Department',
            'short_name' => fake()->lexify('???'),
            'code' => fake()->unique()->bothify('DEP-###'),
            'description' => fake()->sentence(),
            'status' => ActiveStatus::Active->value,
        ];
    }
}
