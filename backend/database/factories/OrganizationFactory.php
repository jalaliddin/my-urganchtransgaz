<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'parent_id' => null,
            'name' => $name,
            'short_name' => fake()->lexify('???'),
            'code' => fake()->unique()->bothify('ORG-###'),
            'type' => OrganizationType::Subordinate->value,
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'director_name' => fake()->name(),
            'status' => ActiveStatus::Active->value,
        ];
    }

    public function central(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => OrganizationType::Central->value,
            'parent_id' => null,
        ]);
    }
}
