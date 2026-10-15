<?php

use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceUser;

function syncRoster(string $token, array $payload)
{
    return test()->withToken($token)->putJson('/api/v1/integrations/attendance/device-users', $payload);
}

it('rejects a roster without a valid device token', function () {
    AttendanceDevice::factory()->withToken('right')->create(['device_id' => 'GATE']);

    syncRoster('wrong', ['device_id' => 'GATE', 'complete' => true, 'users' => []])->assertStatus(401);
});

it('replaces the device\'s roster with a complete list', function () {
    $device = AttendanceDevice::factory()->withToken('t')->create(['device_id' => 'GATE']);
    AttendanceDeviceUser::factory()->create(['attendance_device_id' => $device->id, 'user_id' => '7', 'name' => 'Removed P']);
    AttendanceDeviceUser::factory()->create(['attendance_device_id' => $device->id, 'user_id' => '108', 'name' => 'Old name']);

    syncRoster('t', ['device_id' => 'GATE', 'complete' => true, 'users' => [
        ['user_id' => '108', 'name' => 'Kenjayev N'],
        ['user_id' => '28', 'name' => 'Matyoqubov O', 'card_no' => '12001'],
    ]])->assertOk()->assertJsonPath('data.count', 2);

    expect($device->users()->orderBy('user_id')->pluck('name', 'user_id')->all())->toBe(['108' => 'Kenjayev N', '28' => 'Matyoqubov O']);
    expect($device->fresh()->roster_synced_at)->not->toBeNull();
});

it('only adds and updates from a partial list, never removes', function () {
    $device = AttendanceDevice::factory()->withToken('t')->create(['device_id' => 'GATE']);
    AttendanceDeviceUser::factory()->create(['attendance_device_id' => $device->id, 'user_id' => '7']);

    syncRoster('t', ['device_id' => 'GATE', 'complete' => false, 'users' => [['user_id' => '108', 'name' => 'Kenjayev N']]])
        ->assertOk();

    expect($device->users()->count())->toBe(2);
    expect($device->fresh()->roster_complete)->toBeFalse();
});

it('refuses a roster sent with another device\'s id', function () {
    AttendanceDevice::factory()->withToken('t')->create(['device_id' => 'GATE']);

    syncRoster('t', ['device_id' => 'OTHER', 'complete' => true, 'users' => []])->assertStatus(422);
});
