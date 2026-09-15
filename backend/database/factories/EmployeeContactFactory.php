<?php

namespace Database\Factories;

use App\Enums\ContactType;
use App\Models\Employee;
use App\Models\EmployeeContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeContact>
 */
class EmployeeContactFactory extends Factory
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
            'type' => ContactType::Emergency->value,
            'full_name' => fake()->name(),
            'relationship' => fake()->randomElement(['spouse', 'parent', 'sibling', 'friend']),
            'phone' => fake()->numerify('+998#########'),
            'address' => fake()->address(),
        ];
    }

    public function bank(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ContactType::Bank->value,
            'relationship' => null,
            'address' => null,
            'bank_name' => fake()->company().' Bank',
            'bank_account_number' => fake()->numerify(str_repeat('#', 20)),
        ]);
    }
}
