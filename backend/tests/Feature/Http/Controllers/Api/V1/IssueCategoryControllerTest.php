<?php

use App\Models\Issue;
use App\Models\IssueCategory;
use App\Models\Organization;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 without authentication', function () {
    $this->getJson('/api/v1/issue-categories')->assertStatus(401);
});

it('lets technical-policy, central-admin and super-admin manage categories', function () {
    foreach (['technical-policy', 'central-admin', 'super-admin'] as $role) {
        $user = userWithRole($role, Organization::factory()->create());

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/issue-categories')->assertOk();
    }
});

it('forbids every other role from the category CRUD', function () {
    foreach (['employee', 'department-manager', 'organization-admin', 'hr', 'safety-manager', 'manager'] as $role) {
        $user = userWithRole($role, Organization::factory()->create());
        $category = IssueCategory::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/issue-categories')->assertStatus(403);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/issue-categories', ['name' => "Not {$role}"])->assertStatus(403);
        $this->actingAs($user, 'sanctum')->putJson("/api/v1/issue-categories/{$category->id}", ['name' => 'X'])->assertStatus(403);
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/issue-categories/{$category->id}")->assertStatus(403);
    }
});

it('lists every category including inactive ones, with how many issues use each', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $used = IssueCategory::factory()->create(['sort_order' => 1]);
    IssueCategory::factory()->create(['sort_order' => 2, 'status' => 'inactive']);
    Issue::factory()->count(2)->create(['issue_category_id' => $used->id]);

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->getJson('/api/v1/issue-categories');

    $response->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $used->id)
        ->assertJsonPath('data.0.issues_count', 2);
});

it('creates a category, generating its code from the name and appending the next sort order', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    IssueCategory::factory()->create(['sort_order' => 4]);

    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/issue-categories', ['name' => 'Gaz hidi sezilishi'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'gaz_hidi_sezilishi')
        ->assertJsonPath('data.sort_order', 5)
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('audit_logs', ['module' => 'issue_categories', 'action' => 'created']);
});

it('keeps generated codes unique when two names produce the same slug', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    IssueCategory::factory()->create(['code' => 'gaz_hidi']);

    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/issue-categories', ['name' => 'Gaz hidi'])
        ->assertCreated()->assertJsonPath('data.code', 'gaz_hidi_2');
});

it('requires a unique name', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    IssueCategory::factory()->create(['name' => 'Gaz sizib chiqishi']);

    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/issue-categories', ['name' => 'Gaz sizib chiqishi'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/issue-categories', [])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('updates a category and can deactivate it, without changing its code', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $category = IssueCategory::factory()->create(['code' => 'gas_leak']);

    $this->actingAs($technicalPolicyUser, 'sanctum')->putJson("/api/v1/issue-categories/{$category->id}", [
        'name' => 'Gaz sizishi', 'status' => 'inactive', 'sort_order' => 9,
    ])->assertOk()->assertJsonPath('data.name', 'Gaz sizishi')->assertJsonPath('data.status', 'inactive');

    expect($category->fresh()->code)->toBe('gas_leak');
});

it('lets a category keep its own name on update', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $category = IssueCategory::factory()->create(['name' => 'Same name']);

    $this->actingAs($technicalPolicyUser, 'sanctum')->putJson("/api/v1/issue-categories/{$category->id}", ['name' => 'Same name', 'sort_order' => 3])
        ->assertOk();
});

it('deletes an unused category but refuses to delete one that issues use', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $unused = IssueCategory::factory()->create();
    $used = IssueCategory::factory()->create();
    Issue::factory()->create(['issue_category_id' => $used->id]);

    $this->actingAs($technicalPolicyUser, 'sanctum')->deleteJson("/api/v1/issue-categories/{$unused->id}")->assertOk();
    $this->assertDatabaseMissing('issue_categories', ['id' => $unused->id]);

    $this->actingAs($technicalPolicyUser, 'sanctum')->deleteJson("/api/v1/issue-categories/{$used->id}")->assertStatus(409);
    $this->assertDatabaseHas('issue_categories', ['id' => $used->id]);
});
