<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Task;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when searching without authentication', function () {
    $this->getJson('/api/v1/search?q=test')->assertStatus(401);
});

it('returns no groups for a query shorter than 2 characters', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/search?q=a');

    $response->assertOk()->assertJsonPath('data', []);
});

it('finds a matching task by title', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    Task::factory()->create(['title' => 'Yillik hisobotni tayyorlash', 'creator_id' => $manager->id]);

    $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/search?q=hisobot');

    $response->assertOk();
    $taskGroup = collect($response->json('data'))->firstWhere('type', 'tasks');
    expect($taskGroup)->not->toBeNull();
    expect($taskGroup['results'])->toHaveCount(1);
});

it('does not let an organization-B department-manager find an organization-A employee — the mandatory cross-organization isolation test', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $departmentB = Department::factory()->create(['organization_id' => $organizationB->id]);

    $targetEmployee = Employee::factory()->create([
        'organization_id' => $organizationA->id,
        'first_name' => 'Ulugbek',
        'last_name' => 'Rashidov',
        'employee_number' => 'EMP99001',
    ]);

    $deptManagerB = userWithRole('department-manager', $organizationB, $departmentB);

    $response = $this->actingAs($deptManagerB, 'sanctum')->getJson('/api/v1/search?q=Ulugbek');

    $response->assertOk();
    $employeeGroup = collect($response->json('data'))->firstWhere('type', 'employees');
    expect($employeeGroup)->toBeNull();
});

it('lets a central-admin find an employee regardless of organization', function () {
    $organization = Organization::factory()->create();
    Employee::factory()->create([
        'organization_id' => $organization->id,
        'first_name' => 'Dilshod',
        'last_name' => 'Tashkentov',
    ]);
    $centralAdmin = userWithRole('central-admin');

    $response = $this->actingAs($centralAdmin, 'sanctum')->getJson('/api/v1/search?q=Dilshod');

    $employeeGroup = collect($response->json('data'))->firstWhere('type', 'employees');
    expect($employeeGroup['results'])->toHaveCount(1);
});
