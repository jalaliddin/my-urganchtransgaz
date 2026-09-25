<?php

use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskCategory;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 without authentication', function () {
    $this->getJson('/api/v1/task-categories')->assertUnauthorized();
});

it('lets technical-policy, central-admin and super-admin manage task categories', function () {
    foreach (['technical-policy', 'central-admin', 'super-admin'] as $role) {
        $user = userWithRole($role, Organization::factory()->create());

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/task-categories')->assertOk();
    }
});

it('forbids every other role from the task category CRUD with 403', function () {
    foreach (['employee', 'manager', 'department-manager', 'organization-admin', 'hr', 'safety-manager'] as $role) {
        $user = userWithRole($role, Organization::factory()->create());
        $category = TaskCategory::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/task-categories')->assertForbidden();
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/task-categories', ['name' => "Not {$role}"])->assertForbidden();
        $this->actingAs($user, 'sanctum')->putJson("/api/v1/task-categories/{$category->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/task-categories/{$category->id}")->assertForbidden();
    }

    $this->assertDatabaseCount('task_categories', 6);
});

it('lists every category including inactive ones, with how many tasks use each', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $used = TaskCategory::factory()->create(['sort_order' => 1]);
    TaskCategory::factory()->inactive()->create(['sort_order' => 2]);
    Task::factory()->count(3)->create(['task_category_id' => $used->id]);

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->getJson('/api/v1/task-categories');

    $response->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $used->id)
        ->assertJsonPath('data.0.tasks_count', 3)
        ->assertJsonPath('data.1.status', 'inactive');
});

it('creates a category with a generated code, the next sort order and the default color', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    TaskCategory::factory()->create(['sort_order' => 4]);

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/task-categories', ['name' => 'Gaz quvurini tekshirish']);

    $response->assertCreated()
        ->assertJsonPath('data.code', 'gaz_quvurini_tekshirish')
        ->assertJsonPath('data.sort_order', 5)
        ->assertJsonPath('data.color', '#1E3A5F')
        ->assertJsonPath('data.status', 'active');
    $this->assertDatabaseHas('task_categories', ['name' => 'Gaz quvurini tekshirish', 'code' => 'gaz_quvurini_tekshirish']);
    $this->assertDatabaseHas('audit_logs', ['module' => 'task_categories', 'action' => 'created']);
});

it('keeps generated codes unique when two names produce the same slug', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    TaskCategory::factory()->create(['code' => 'hisobot']);

    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/task-categories', ['name' => 'Hisobot'])
        ->assertCreated()->assertJsonPath('data.code', 'hisobot_2');
});

it('rejects a duplicate name or a missing name with 422', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    TaskCategory::factory()->create(['name' => 'Ta\'mirlash ishlari']);

    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/task-categories', ['name' => 'Ta\'mirlash ishlari'])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/task-categories', [])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('rejects a color that is not a #RRGGBB hex value with 422', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());

    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/task-categories', ['name' => 'Yangi', 'color' => 'red'])
        ->assertUnprocessable()->assertJsonValidationErrors('color');

    $this->assertDatabaseMissing('task_categories', ['name' => 'Yangi']);
});

it('updates the name, color and status of a category without changing its code', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $category = TaskCategory::factory()->create(['name' => 'Eski nom', 'code' => 'eski_nom', 'color' => '#000000']);

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->putJson("/api/v1/task-categories/{$category->id}", [
        'name' => 'Yangi nom',
        'color' => '#E53935',
        'status' => 'inactive',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Yangi nom')
        ->assertJsonPath('data.code', 'eski_nom')
        ->assertJsonPath('data.color', '#E53935')
        ->assertJsonPath('data.status', 'inactive');
    $this->assertDatabaseHas('task_categories', ['id' => $category->id, 'name' => 'Yangi nom', 'code' => 'eski_nom', 'status' => 'inactive']);
});

it('deletes an unused category', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $category = TaskCategory::factory()->create();

    $this->actingAs($technicalPolicyUser, 'sanctum')->deleteJson("/api/v1/task-categories/{$category->id}")->assertOk();

    $this->assertModelMissing($category);
    $this->assertDatabaseHas('audit_logs', ['module' => 'task_categories', 'action' => 'deleted']);
});

it('refuses with 409 to delete a category that tasks use, and keeps it', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $category = TaskCategory::factory()->create();
    Task::factory()->create(['task_category_id' => $category->id]);

    $this->actingAs($technicalPolicyUser, 'sanctum')->deleteJson("/api/v1/task-categories/{$category->id}")
        ->assertConflict()
        ->assertJsonPath('message', 'Bu kategoriya topshiriqlarda ishlatilgan, o\'chirib bo\'lmaydi. Uni faolsiz qiling.');

    $this->assertModelExists($category);
});
