<?php

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Task;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 without authentication', function () {
    $this->getJson('/api/v1/reports/overview')->assertStatus(401);
});

it('forbids a non-central organization-admin from viewing the company-wide overview', function () {
    $orgAdmin = userWithRole('organization-admin', Organization::factory()->create());

    $this->actingAs($orgAdmin, 'sanctum')->getJson('/api/v1/reports/overview')->assertStatus(403);
});

it('aggregates employee counts by organization and task counts by status for a central-admin', function () {
    $organization = Organization::factory()->create();
    Employee::factory()->count(3)->create(['organization_id' => $organization->id]);
    Task::factory()->create(['status' => 'new']);
    Task::factory()->create(['status' => 'new']);
    Task::factory()->create(['status' => 'completed']);

    $centralAdmin = userWithRole('central-admin');

    $response = $this->actingAs($centralAdmin, 'sanctum')->getJson('/api/v1/reports/overview');

    $response->assertOk();
    $byOrg = collect($response->json('data.employees_by_organization'))->firstWhere('label', $organization->name);
    expect((int) $byOrg['total'])->toBe(3);

    $byStatus = collect($response->json('data.tasks_by_status'))->keyBy('status');
    expect((int) $byStatus['new']['total'])->toBe(2);
    expect((int) $byStatus['completed']['total'])->toBe(1);
});
