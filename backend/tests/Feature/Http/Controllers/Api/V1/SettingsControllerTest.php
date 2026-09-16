<?php

use App\Models\Organization;
use App\Models\Setting;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when reading settings without authentication', function () {
    $this->getJson('/api/v1/settings')->assertStatus(401);
});

it('forbids a plain employee from viewing settings', function () {
    $employee = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($employee, 'sanctum')->getJson('/api/v1/settings')->assertStatus(403);
});

it('falls back to the config default when a setting was never set', function () {
    $centralAdmin = userWithRole('central-admin');

    $response = $this->actingAs($centralAdmin, 'sanctum')->getJson('/api/v1/settings');

    $response->assertOk();
    $values = $response->json('data.values');
    expect($values['attendance.work_start'])->toBe(config('attendance.work_start'));
    expect($values['attendance.working_days'])->toBe([1, 2, 3, 4, 5]);
});

it('lets a central-admin update settings and the new value is read back immediately', function () {
    $centralAdmin = userWithRole('central-admin');

    $this->actingAs($centralAdmin, 'sanctum')->putJson('/api/v1/settings', [
        'values' => [
            'attendance.work_start' => '08:30',
            'attendance.working_days' => [1, 2, 3, 4, 5, 6],
        ],
    ])->assertOk();

    expect(Setting::get('attendance.work_start'))->toBe('08:30');
    expect(Setting::get('attendance.working_days'))->toBe([1, 2, 3, 4, 5, 6]);

    $this->assertDatabaseHas('audit_logs', ['action' => 'updated', 'module' => 'settings']);
});

it('ignores an unknown key on update', function () {
    $centralAdmin = userWithRole('central-admin');

    $this->actingAs($centralAdmin, 'sanctum')->putJson('/api/v1/settings', [
        'values' => ['not.a.real.key' => 'value'],
    ])->assertOk();

    expect(Setting::where('key', 'not.a.real.key')->exists())->toBeFalse();
});

it('forbids an organization-admin from updating settings', function () {
    $orgAdmin = userWithRole('organization-admin', Organization::factory()->create());

    $this->actingAs($orgAdmin, 'sanctum')->putJson('/api/v1/settings', [
        'values' => ['attendance.work_start' => '10:00'],
    ])->assertStatus(403);
});
