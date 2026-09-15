<?php

namespace Database\Factories;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 month', 'now');
        $checkIn = (clone $date)->setTime(9, 0);
        $checkOut = (clone $date)->setTime(18, 0);

        return [
            'employee_id' => Employee::factory(),
            'date' => $date->format('Y-m-d'),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'worked_minutes' => 540,
            'status' => AttendanceStatus::Present->value,
            'source' => AttendanceSource::Web->value,
        ];
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'check_in' => null,
            'check_out' => null,
            'worked_minutes' => null,
            'status' => AttendanceStatus::Absent->value,
        ]);
    }
}
