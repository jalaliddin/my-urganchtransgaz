<?php

use App\Models\AuditLog;
use App\Models\Organization;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing audit logs without authentication', function () {
    $this->getJson('/api/v1/audit-logs')->assertStatus(401);
});

it('forbids a plain employee from viewing audit logs', function () {
    $employee = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($employee, 'sanctum')->getJson('/api/v1/audit-logs')->assertStatus(403);
});

it('forbids an organization-admin from viewing audit logs — central-admin only', function () {
    $orgAdmin = userWithRole('organization-admin', Organization::factory()->create());

    $this->actingAs($orgAdmin, 'sanctum')->getJson('/api/v1/audit-logs')->assertStatus(403);
});

it('lets a central-admin filter audit logs by module and action', function () {
    $centralAdmin = userWithRole('central-admin');
    AuditLog::factory()->create(['module' => 'tasks', 'action' => 'created']);
    AuditLog::factory()->create(['module' => 'documents', 'action' => 'uploaded']);

    $response = $this->actingAs($centralAdmin, 'sanctum')->getJson('/api/v1/audit-logs?module=tasks');

    $response->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.module', 'tasks');
});

it('exports audit logs as csv', function () {
    $centralAdmin = userWithRole('central-admin');
    AuditLog::factory()->create(['module' => 'tasks', 'action' => 'created']);

    $response = $this->actingAs($centralAdmin, 'sanctum')->get('/api/v1/audit-logs?export=csv');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
});
