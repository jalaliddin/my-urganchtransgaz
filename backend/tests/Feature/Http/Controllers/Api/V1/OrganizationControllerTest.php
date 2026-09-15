<?php

use App\Models\Organization;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing organizations without authentication', function () {
    $this->getJson('/api/v1/organizations')->assertStatus(401);
});

it('forbids an employee without the organizations.view permission', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/organizations')
        ->assertStatus(403);
});

it('lets a central-admin see every organization', function () {
    $central = Organization::factory()->central()->create();
    Organization::factory()->count(3)->create();
    $user = userWithRole('central-admin', $central);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/organizations')
        ->assertOk()
        ->assertJsonPath('meta.total', 4);
});

it('scopes an organization-admin to only their own organization', function () {
    $ownOrg = Organization::factory()->create();
    Organization::factory()->count(2)->create();
    $user = userWithRole('organization-admin', $ownOrg);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/organizations')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $ownOrg->id);
});

it('forbids an organization-admin from viewing another organization directly', function () {
    $ownOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $user = userWithRole('organization-admin', $ownOrg);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/organizations/{$otherOrg->id}")
        ->assertStatus(403);
});

it('allows a central-admin to create an organization', function () {
    $user = userWithRole('central-admin', Organization::factory()->central()->create());

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/organizations', [
            'name' => 'Yangi Filial',
            'code' => 'UTG-999',
            'type' => 'subordinate',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('organizations', ['code' => 'UTG-999']);
});

it('forbids an organization-admin from creating an organization', function () {
    $user = userWithRole('organization-admin', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/organizations', [
            'name' => 'Yangi Filial',
            'code' => 'UTG-999',
            'type' => 'subordinate',
        ])
        ->assertStatus(403);
});

it('validates required fields when creating an organization', function () {
    $user = userWithRole('central-admin', Organization::factory()->central()->create());

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/organizations', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'code', 'type']);
});

it('rejects a duplicate organization code', function () {
    Organization::factory()->create(['code' => 'DUPLICATE']);
    $user = userWithRole('central-admin', Organization::factory()->central()->create());

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/organizations', [
            'name' => 'Another Org',
            'code' => 'DUPLICATE',
            'type' => 'subordinate',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('updates an organization and persists the change', function () {
    $organization = Organization::factory()->create(['director_name' => 'Old Name']);
    $user = userWithRole('central-admin', Organization::factory()->central()->create());

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/organizations/{$organization->id}", ['director_name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.director_name', 'New Name');

    $this->assertDatabaseHas('organizations', ['id' => $organization->id, 'director_name' => 'New Name']);
});

it('soft deletes an organization', function () {
    $organization = Organization::factory()->create();
    $user = userWithRole('central-admin', Organization::factory()->central()->create());

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/organizations/{$organization->id}")
        ->assertOk();

    $this->assertSoftDeleted('organizations', ['id' => $organization->id]);
});

it('logs an audit entry when an organization is created', function () {
    $user = userWithRole('central-admin', Organization::factory()->central()->create());

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/organizations', [
        'name' => 'Audit Org',
        'code' => 'AUDIT-1',
        'type' => 'subordinate',
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'action' => 'created',
        'module' => 'organizations',
    ]);
});
