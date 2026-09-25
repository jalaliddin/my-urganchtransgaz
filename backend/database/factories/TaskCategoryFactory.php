<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\TaskCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskCategory>
 */
class TaskCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'code' => fake()->unique()->bothify('task_cat_###'),
            'color' => fake()->hexColor(),
            'sort_order' => 0,
            'status' => ActiveStatus::Active->value,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ActiveStatus::Inactive->value]);
    }
}
