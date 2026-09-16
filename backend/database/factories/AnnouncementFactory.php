<?php

namespace Database\Factories;

use App\Enums\AnnouncementStatus;
use App\Enums\TaskPriority;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(3, true),
            'author_id' => User::factory(),
            'priority' => TaskPriority::Normal->value,
            'status' => AnnouncementStatus::Draft->value,
            'publish_at' => null,
            'expire_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AnnouncementStatus::Published->value,
            'publish_at' => now()->subMinute(),
        ]);
    }
}
