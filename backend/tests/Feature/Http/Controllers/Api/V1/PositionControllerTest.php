<?php

use App\Models\Organization;
use App\Models\Position;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing positions without authentication', function () {
    $this->getJson('/api/v1/positions')->assertStatus(401);
});

it('scopes an organization-admin to positions within their own organization', function () {
    $ownOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    Position::factory()->count(2)->create(['organization_id' => $ownOrg->id]);
    Position::factory()->count(3)->create(['organization_id' => $otherOrg->id]);
    $user = userWithRole('organization-admin', $ownOrg);

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/positions')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('lets central access see positions across every organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    Position::factory()->create(['organization_id' => $orgA->id]);
    Position::factory()->create(['organization_id' => $orgB->id]);
    $user = userWithRole('hr', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/positions')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('creates a position within the creator organization', function () {
    $organization = Organization::factory()->create();
    $user = userWithRole('hr', $organization);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/positions', [
            'organization_id' => $organization->id,
            'title' => 'Bosh mutaxassis',
            'code' => 'BM',
        ])
        ->assertCreated()
        ->assertJsonPath('data.organization_id', $organization->id)
        ->assertJsonPath('data.title', 'Bosh mutaxassis');
});

it('forbids an organization-admin from creating a position in another organization', function () {
    $ownOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $user = userWithRole('organization-admin', $ownOrg);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/positions', [
            'organization_id' => $otherOrg->id,
            'title' => 'Muhandis',
        ])
        ->assertStatus(403);
});

it('forbids a department-manager (view-only) from creating a position', function () {
    $organization = Organization::factory()->create();
    $user = userWithRole('department-manager', $organization);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/positions', [
            'organization_id' => $organization->id,
            'title' => 'Muhandis',
        ])
        ->assertStatus(403);
});

it('rejects a duplicate position code within the same organization', function () {
    $organization = Organization::factory()->create();
    Position::factory()->create(['organization_id' => $organization->id, 'code' => 'DUP']);
    $user = userWithRole('hr', $organization);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/positions', [
            'organization_id' => $organization->id,
            'title' => 'Another',
            'code' => 'DUP',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

it('allows the same position code in two different organizations', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    Position::factory()->create(['organization_id' => $orgA->id, 'code' => 'SAME']);
    $user = userWithRole('hr', $orgB);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/positions', [
            'organization_id' => $orgB->id,
            'title' => 'Another',
            'code' => 'SAME',
        ])
        ->assertCreated();
});

it('updates a position and persists the change', function () {
    $organization = Organization::factory()->create();
    $position = Position::factory()->create(['organization_id' => $organization->id, 'title' => 'Old']);
    $user = userWithRole('hr', $organization);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/positions/{$position->id}", ['title' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.title', 'New');
});

it('deletes a position', function () {
    $organization = Organization::factory()->create();
    $position = Position::factory()->create(['organization_id' => $organization->id]);
    $user = userWithRole('central-admin', $organization);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/positions/{$position->id}")
        ->assertOk();

    $this->assertDatabaseMissing('positions', ['id' => $position->id]);
});

it('forbids an organization-admin from deleting a position in their own organization', function () {
    $organization = Organization::factory()->create();
    $position = Position::factory()->create(['organization_id' => $organization->id]);
    $user = userWithRole('organization-admin', $organization);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/positions/{$position->id}")
        ->assertStatus(403);
});
