<?php

namespace Database\Factories;

use App\Models\AttendanceDevice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttendanceDevice>
 */
class AttendanceDeviceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => 'DEV-'.fake()->unique()->numerify('####'),
            'name' => fake()->words(2, true).' entrance',
            'organization_id' => null,
            'token' => hash('sha256', Str::random(40)),
            'is_active' => true,
        ];
    }

    /**
     * Attach a known plaintext token (hashed for storage) so a test can
     * authenticate as this device.
     */
    public function withToken(string $plainTextToken): static
    {
        return $this->state(fn (array $attributes) => [
            'token' => hash('sha256', $plainTextToken),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
