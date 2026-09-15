<?php

namespace Database\Factories;

use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Models\Employee;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(Gender::cases());
        $firstName = $gender === Gender::Male ? fake()->firstNameMale() : fake()->firstNameFemale();
        $lastName = fake()->lastName();

        return [
            'user_id' => null,
            'organization_id' => Organization::factory(),
            'department_id' => null,
            'position_id' => null,
            'employee_number' => 'EMP'.fake()->unique()->numerify('#####'),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_name' => fake()->lastName(),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'birth_place' => fake()->city(),
            'gender' => $gender->value,
            'phone' => fake()->numerify('+998#########'),
            'email' => fake()->unique()->safeEmail(),
            'corporate_email' => fake()->unique()->userName().'@urtg.uz',
            'address' => fake()->address(),
            'passport_number' => strtoupper(fake()->bothify('??#######')),
            'pinfl' => fake()->numerify('##############'),
            'employment_type' => EmploymentType::FullTime->value,
            'hire_date' => fake()->dateTimeBetween('-10 years', 'now'),
            'termination_date' => null,
            'photo' => null,
            'status' => EmployeeStatus::Active->value,
        ];
    }
}
