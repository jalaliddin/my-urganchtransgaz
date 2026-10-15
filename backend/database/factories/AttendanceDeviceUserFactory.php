<?php

namespace Database\Factories;

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceDeviceUser>
 */
class AttendanceDeviceUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attendance_device_id' => AttendanceDevice::factory(),
            'user_id' => (string) fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->lastName().' '.strtoupper(fake()->randomLetter()),
            'card_no' => null,
        ];
    }
}
