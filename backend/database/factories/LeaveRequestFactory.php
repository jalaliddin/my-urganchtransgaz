<?php

namespace Database\Factories;

use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveRequestType;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
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
            'type' => LeaveRequestType::Vacation->value,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'reason' => fake()->sentence(),
            'status' => LeaveRequestStatus::Pending->value,
        ];
    }

    public function departmentApproved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => LeaveRequestStatus::DepartmentApproved->value]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => LeaveRequestStatus::Approved->value]);
    }
}
