<?php

namespace Database\Factories;

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceSource;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceEvent>
 */
class AttendanceEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => fake()->randomElement(AttendanceEventType::cases())->value,
            'occurred_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'source' => AttendanceSource::Biometric->value,
        ];
    }

    public function checkIn(): static
    {
        return $this->state(fn () => ['type' => AttendanceEventType::CheckIn->value]);
    }

    public function checkOut(): static
    {
        return $this->state(fn () => ['type' => AttendanceEventType::CheckOut->value]);
    }
}
