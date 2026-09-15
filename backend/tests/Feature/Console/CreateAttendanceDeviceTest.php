<?php

use App\Models\AttendanceDevice;
use App\Models\Organization;

it('creates a device and prints a one-time token', function () {
    $this->artisan('attendance:create-device', ['device_id' => 'DEV-1', 'name' => 'Main entrance'])
        ->assertSuccessful()
        ->expectsOutputToContain('Device created.');

    $this->assertDatabaseHas('attendance_devices', ['device_id' => 'DEV-1', 'name' => 'Main entrance', 'is_active' => true]);
});

it('refuses to create a device with a duplicate device_id', function () {
    AttendanceDevice::factory()->create(['device_id' => 'DEV-1']);

    $this->artisan('attendance:create-device', ['device_id' => 'DEV-1', 'name' => 'Another'])
        ->assertFailed();
});

it('refuses an organization_id that does not exist', function () {
    $this->artisan('attendance:create-device', ['device_id' => 'DEV-1', 'name' => 'Main', '--organization_id' => 999])
        ->assertFailed();
});

it('links the device to a real organization when given', function () {
    $organization = Organization::factory()->create();

    $this->artisan('attendance:create-device', ['device_id' => 'DEV-1', 'name' => 'Main', '--organization_id' => $organization->id])
        ->assertSuccessful();

    $this->assertDatabaseHas('attendance_devices', ['device_id' => 'DEV-1', 'organization_id' => $organization->id]);
});
