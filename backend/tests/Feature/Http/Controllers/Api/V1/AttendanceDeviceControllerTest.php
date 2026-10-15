<?php

use App\Models\AttendanceDevice;
use App\Models\Organization;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** What the webhook says to a token: 422 (accepted, the empty event is invalid) or 401. */
function webhookStatusFor(string $token): int
{
    return test()->withToken($token)->postJson('/api/v1/integrations/attendance/events', [])->status();
}

it('returns 401 when listing devices without authentication', function () {
    $this->getJson('/api/v1/attendance-devices')->assertStatus(401);
});

it('lets hr add a device and returns a token that the webhook accepts', function () {
    $hr = userWithRole('hr', Organization::factory()->create());

    $response = $this->actingAs($hr, 'sanctum')->postJson('/api/v1/attendance-devices', [
        'device_id' => 'TURNSTILE-3',
        'name' => 'Ombor darvozasi',
    ])->assertCreated()->assertJsonPath('data.device.device_id', 'TURNSTILE-3');

    $token = $response->json('data.token');
    expect($token)->toBeString()->toHaveLength(40);
    expect(AttendanceDevice::firstWhere('device_id', 'TURNSTILE-3')->token)->toBe(hash('sha256', $token));

    $this->app['auth']->forgetGuards();
    expect(webhookStatusFor($token))->toBe(422);
});

it('lists devices without ever including the token', function () {
    AttendanceDevice::factory()->withToken('secret')->create(['device_id' => 'TURNSTILE-1']);
    $hr = userWithRole('hr', Organization::factory()->create());

    $response = $this->actingAs($hr, 'sanctum')->getJson('/api/v1/attendance-devices')
        ->assertOk()
        ->assertJsonPath('data.0.device_id', 'TURNSTILE-1');

    expect($response->json('data.0'))->not->toHaveKey('token');
});

it('regenerates a token so the old one stops working at once', function () {
    $device = AttendanceDevice::factory()->withToken('old-token')->create(['device_id' => 'TURNSTILE-1']);
    $hr = userWithRole('hr', Organization::factory()->create());

    $newToken = $this->actingAs($hr, 'sanctum')
        ->postJson("/api/v1/attendance-devices/{$device->id}/token")
        ->assertOk()
        ->json('data.token');

    $this->app['auth']->forgetGuards();
    expect(webhookStatusFor('old-token'))->toBe(401);
    expect(webhookStatusFor($newToken))->toBe(422);
});

it('turns a device off so its token is refused', function () {
    $device = AttendanceDevice::factory()->withToken('a-token')->create(['device_id' => 'TURNSTILE-1']);
    $hr = userWithRole('hr', Organization::factory()->create());

    $this->actingAs($hr, 'sanctum')
        ->patchJson("/api/v1/attendance-devices/{$device->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    $this->app['auth']->forgetGuards();
    expect(webhookStatusFor('a-token'))->toBe(401);
});

it('refuses a device_id that is taken or not a plain identifier', function (string $deviceId) {
    AttendanceDevice::factory()->create(['device_id' => 'TURNSTILE-1']);
    $hr = userWithRole('hr', Organization::factory()->create());

    $this->actingAs($hr, 'sanctum')->postJson('/api/v1/attendance-devices', ['device_id' => $deviceId, 'name' => 'X'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('device_id');
})->with(['TURNSTILE-1', 'gate one', '<b>']);

it('keeps organization admins out, though they manage their own attendance', function () {
    $device = AttendanceDevice::factory()->create();
    $orgAdmin = userWithRole('organization-admin', Organization::factory()->create());

    $this->actingAs($orgAdmin, 'sanctum')->getJson('/api/v1/attendance-devices')->assertForbidden();
    $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/attendance-devices', ['device_id' => 'X-1', 'name' => 'X'])->assertForbidden();
    $this->actingAs($orgAdmin, 'sanctum')->postJson("/api/v1/attendance-devices/{$device->id}/token")->assertForbidden();
    $this->actingAs($orgAdmin, 'sanctum')->patchJson("/api/v1/attendance-devices/{$device->id}", ['is_active' => false])->assertForbidden();

    expect($device->fresh()->is_active)->toBeTrue();
});
