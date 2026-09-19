<?php

namespace Database\Factories;

use App\Enums\IssueStatus;
use App\Models\Employee;
use App\Models\Issue;
use App\Models\IssueCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_employee_id' => Employee::factory(),
            'organization_id' => Organization::factory(),
            'issue_category_id' => IssueCategory::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'object_name' => fake()->streetName(),
            'latitude' => fake()->latitude(41.0, 42.5),
            'longitude' => fake()->longitude(60.0, 61.5),
            'status' => IssueStatus::Open->value,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IssueStatus::Resolved->value,
            'resolution_note' => fake()->sentence(),
            'resolved_at' => now(),
        ]);
    }
}
