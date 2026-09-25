<?php

namespace Database\Factories;

use App\Enums\AbsenceType;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeAbsence>
 */
class EmployeeAbsenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 week', '+2 months');

        return [
            'employee_id' => Employee::factory(),
            'type' => AbsenceType::AnnualLeave->value,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => (clone $start)->modify('+13 days')->format('Y-m-d'),
            'document_number' => fake()->numerify('###-K'),
            'document_date' => $start->format('Y-m-d'),
            'created_by' => User::factory(),
        ];
    }

    public function ofType(AbsenceType $type): static
    {
        return $this->state(fn (array $attributes) => ['type' => $type->value]);
    }

    public function between(string $start, string $end): static
    {
        return $this->state(fn (array $attributes) => ['start_date' => $start, 'end_date' => $end]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'cancelled_at' => now(),
            'cancelled_by' => User::factory(),
            'cancellation_reason' => 'Buyruq bekor qilindi',
        ]);
    }
}
