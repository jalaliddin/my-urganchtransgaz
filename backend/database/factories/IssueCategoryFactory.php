<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\IssueCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssueCategory>
 */
class IssueCategoryFactory extends Factory
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
            'code' => fake()->unique()->bothify('cat_###'),
            'sort_order' => 0,
            'status' => ActiveStatus::Active->value,
        ];
    }
}
