<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
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
            'document_type_id' => DocumentType::factory(),
            'title' => fake()->words(3, true),
            'document_number' => fake()->bothify('??######'),
            'issue_date' => fake()->dateTimeBetween('-5 years', '-1 year'),
            'expiry_date' => fake()->dateTimeBetween('+1 month', '+5 years'),
            'file_path' => 'employee-documents/test/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 2_000_000),
            'status' => DocumentStatus::Pending->value,
            'uploaded_by' => User::factory(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Approved->value,
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }
}
