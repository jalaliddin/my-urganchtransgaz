<?php

namespace Database\Factories;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\AnnouncementTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnnouncementTarget>
 */
class AnnouncementTargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'announcement_id' => Announcement::factory(),
            'target_type' => AnnouncementTargetType::Everyone->value,
            'target_id' => null,
        ];
    }

    public function organization(int $organizationId): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => AnnouncementTargetType::Organization->value,
            'target_id' => $organizationId,
        ]);
    }

    public function department(int $departmentId): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => AnnouncementTargetType::Department->value,
            'target_id' => $departmentId,
        ]);
    }

    public function employee(int $employeeId): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => AnnouncementTargetType::Employee->value,
            'target_id' => $employeeId,
        ]);
    }

    public function role(int $roleId): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => AnnouncementTargetType::Role->value,
            'target_id' => $roleId,
        ]);
    }

    public function central(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_type' => AnnouncementTargetType::Central->value,
            'target_id' => null,
        ]);
    }
}
