<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'code' => fake()->unique()->bothify('DOC-###'),
            'requires_expiry' => fake()->boolean(),
            'status' => ActiveStatus::Active->value,
        ];
    }
}
