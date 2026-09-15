<?php

use App\Models\Department;
use App\Models\Organization;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing departments without authentication', function () {
    $this->getJson('/api/v1/departments')->assertStatus(401);
});

it('scopes an organization-admin to departments within their own organization', function () {
    $ownOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    Department::factory()->count(2)->create(['organization_id' => $ownOrg->id]);
    Department::factory()->count(3)->create(['organization_id' => $otherOrg->id]);
    $user = userWithRole('organization-admin', $ownOrg);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/departments')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('scopes a department-manager to only their own department', function () {
    $organization = Organization::factory()->create();
    $ownDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    Department::factory()->count(2)->create(['organization_id' => $organization->id]);
    $user = userWithRole('department-manager', $organization, $ownDepartment);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/departments')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $ownDepartment->id);
});

it('forbids a department-manager from viewing a different department in their own organization', function () {
    $organization = Organization::factory()->create();
    $ownDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $otherDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $user = userWithRole('department-manager', $organization, $ownDepartment);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/departments/{$otherDepartment->id}")
        ->assertStatus(403);
});

it('creates a department within the creator organization', function () {
    $organization = Organization::factory()->create();
    $user = userWithRole('organization-admin', $organization);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/departments', [
            'organization_id' => $organization->id,
            'name' => 'Yangi bo\'lim',
            'code' => 'YB',
        ])
        ->assertCreated()
        ->assertJsonPath('data.organization_id', $organization->id);
});

it('forbids an organization-admin from creating a department in another organization', function () {
    $ownOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $user = userWithRole('organization-admin', $ownOrg);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/departments', [
            'organization_id' => $otherOrg->id,
            'name' => 'Yangi bo\'lim',
            'code' => 'YB',
        ])
        ->assertStatus(403);
});

it('rejects a duplicate department code within the same organization', function () {
    $organization = Organization::factory()->create();
    Department::factory()->create(['organization_id' => $organization->id, 'code' => 'DUP']);
    $user = userWithRole('organization-admin', $organization);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/departments', [
            'organization_id' => $organization->id,
            'name' => 'Another',
            'code' => 'DUP',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('allows the same department code in two different organizations', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    Department::factory()->create(['organization_id' => $orgA->id, 'code' => 'SAME']);
    $user = userWithRole('organization-admin', $orgB);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/departments', [
            'organization_id' => $orgB->id,
            'name' => 'Another',
            'code' => 'SAME',
        ])
        ->assertCreated();
});

it('updates a department and persists the change', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id, 'name' => 'Old']);
    $user = userWithRole('organization-admin', $organization);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/departments/{$department->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New');
});

it('soft deletes a department', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $user = userWithRole('central-admin', $organization);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/departments/{$department->id}")
        ->assertOk();

    $this->assertSoftDeleted('departments', ['id' => $department->id]);
});

it('forbids an organization-admin from deleting a department in their own organization', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $user = userWithRole('organization-admin', $organization);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/departments/{$department->id}")
        ->assertStatus(403);
});
