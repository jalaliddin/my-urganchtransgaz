<?php

namespace Database\Factories;

use App\Enums\BusinessTripStatus;
use App\Models\BusinessTrip;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessTrip>
 */
class BusinessTripFactory extends Factory
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
            'destination' => fake()->city(),
            'purpose' => fake()->sentence(),
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'order_number' => fake()->bothify('ORD-####'),
            'status' => BusinessTripStatus::Scheduled->value,
            'created_by' => User::factory(),
        ];
    }
}
