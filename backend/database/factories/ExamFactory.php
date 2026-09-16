<?php

namespace Database\Factories;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'created_by' => User::factory(),
            'duration_minutes' => 30,
            'passing_score' => 70,
            'attempts_allowed' => 1,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => ExamStatus::Active->value,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ExamStatus::Draft->value]);
    }
}
