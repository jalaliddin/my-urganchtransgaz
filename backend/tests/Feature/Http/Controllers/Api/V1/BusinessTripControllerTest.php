<?php

use App\Models\BusinessTrip;
use App\Models\Organization;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing business trips without authentication', function () {
    $this->getJson('/api/v1/business-trips')->assertStatus(401);
});

it('lets an organization-admin create a business trip for an employee in their own organization', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $employee = userWithRole('employee', $organization);

    $response = $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/business-trips', [
        'employee_id' => $employee->employee->id,
        'destination' => 'Toshkent',
        'purpose' => 'Konferensiya',
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'order_number' => 'ORD-2026-001',
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'scheduled');
});

it('forbids a plain employee from creating a business trip', function () {
    $organization = Organization::factory()->create();
    $employee = userWithRole('employee', $organization);

    $this->actingAs($employee, 'sanctum')->postJson('/api/v1/business-trips', [
        'employee_id' => $employee->employee->id,
        'destination' => 'Toshkent',
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
    ])->assertStatus(403);
});

it('forbids an organization-admin from creating a business trip for an employee in a different organization — the mandatory cross-organization isolation test', function () {
    $ownOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $ownOrganization);
    $employee = userWithRole('employee', $otherOrganization);

    $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/business-trips', [
        'employee_id' => $employee->employee->id,
        'destination' => 'Toshkent',
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
    ])->assertStatus(403);

    $this->actingAs($orgAdmin, 'sanctum')->getJson('/api/v1/business-trips')
        ->assertOk()->assertJsonPath('meta.total', 0);
});

it('lets an employee see their own trip and central/HR cancel it, blocking a second cancel', function () {
    $organization = Organization::factory()->create();
    $employee = userWithRole('employee', $organization);
    $trip = BusinessTrip::factory()->create(['employee_id' => $employee->employee->id]);

    $this->actingAs($employee, 'sanctum')->getJson("/api/v1/business-trips/{$trip->id}")->assertOk();

    $hr = userWithRole('hr');
    $this->actingAs($hr, 'sanctum')->postJson("/api/v1/business-trips/{$trip->id}/cancel")
        ->assertOk()->assertJsonPath('data.status', 'cancelled');

    $this->actingAs($hr, 'sanctum')->postJson("/api/v1/business-trips/{$trip->id}/cancel")
        ->assertStatus(409);
});

it('lists only currently-scheduled upcoming trips for the dashboard widget', function () {
    $organization = Organization::factory()->create();
    $employee = userWithRole('employee', $organization);

    BusinessTrip::factory()->create([
        'employee_id' => $employee->employee->id,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
    ]);
    BusinessTrip::factory()->create([
        'employee_id' => $employee->employee->id,
        'status' => 'cancelled',
        'start_date' => now()->addDays(1)->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
    ]);
    BusinessTrip::factory()->create([
        'employee_id' => $employee->employee->id,
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDays(8)->toDateString(),
    ]);

    $response = $this->actingAs($employee, 'sanctum')->getJson('/api/v1/business-trips/upcoming');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});
