<?php

namespace Database\Factories;

use App\Enums\ChangeRequestStatus;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeChangeRequest>
 */
class EmployeeChangeRequestFactory extends Factory
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
            'requested_by' => User::factory(),
            'changes' => ['first_name' => fake()->firstName()],
            'status' => ChangeRequestStatus::Pending->value,
        ];
    }
}
