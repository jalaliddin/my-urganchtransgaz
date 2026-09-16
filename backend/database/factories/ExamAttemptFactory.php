<?php

namespace Database\Factories;

use App\Enums\AttemptStatus;
use App\Models\Employee;
use App\Models\Exam;
use App\Models\ExamAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAttempt>
 */
class ExamAttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'employee_id' => Employee::factory(),
            'status' => AttemptStatus::InProgress->value,
            'started_at' => now(),
        ];
    }

    public function completed(int $score = 80, bool $passed = true): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttemptStatus::Completed->value,
            'score' => $score,
            'percentage' => $score,
            'passed' => $passed,
            'completed_at' => now(),
        ]);
    }
}
