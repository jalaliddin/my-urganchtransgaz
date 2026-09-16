<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'creator_id' => User::factory(),
            'priority' => TaskPriority::Normal->value,
            'status' => TaskStatus::New->value,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'progress' => 0,
        ];
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => now()->subDays(2)->toDateString(),
            'status' => TaskStatus::InProgress->value,
        ]);
    }
}
