<?php

use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing employees without authentication', function () {
    $this->getJson('/api/v1/employees')->assertStatus(401);
});

it('an organization A user cannot view an organization B employee', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $userA = userWithRole('organization-admin', $organizationA);
    $employeeB = Employee::factory()->create(['organization_id' => $organizationB->id]);

    $this->actingAs($userA, 'sanctum')
        ->getJson("/api/v1/employees/{$employeeB->id}")
        ->assertStatus(403);
});

it('an organization A user does not see organization B employees in the index listing', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $userA = userWithRole('organization-admin', $organizationA);
    Employee::factory()->count(3)->create(['organization_id' => $organizationB->id]);
    Employee::factory()->count(2)->create(['organization_id' => $organizationA->id]);

    $response = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/employees');

    $response->assertOk()->assertJsonPath('meta.total', 3);
    collect($response->json('data'))->each(
        fn ($employee) => expect($employee['organization_id'])->toBe($organizationA->id)
    );
});

it('cannot bypass organization isolation by changing the id in the url', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $userA = userWithRole('organization-admin', $organizationA);

    $employeesInB = Employee::factory()->count(5)->create(['organization_id' => $organizationB->id]);

    foreach ($employeesInB as $employee) {
        $this->actingAs($userA, 'sanctum')
            ->getJson("/api/v1/employees/{$employee->id}")
            ->assertStatus(403);
    }
});

it('lets an employee view their own profile without the employees.view permission', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id, 'organization_id' => $organization->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/employees/{$employee->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $employee->id);
});

it('forbids an employee from viewing a colleague profile', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('employee');
    Employee::factory()->create(['user_id' => $user->id, 'organization_id' => $organization->id]);
    $colleague = Employee::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/employees/{$colleague->id}")
        ->assertStatus(403);
});

it('never exposes passport_number or pinfl in the API response', function () {
    $organization = Organization::factory()->create();
    $user = userWithRole('hr', $organization);
    $employee = Employee::factory()->create([
        'organization_id' => $organization->id,
        'passport_number' => 'AB1234567',
        'pinfl' => '12345678901234',
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/employees/{$employee->id}");

    $response->assertOk();
    expect($response->json('data'))
        ->not->toHaveKey('passport_number')
        ->not->toHaveKey('pinfl');
});

it('creates an employee with a linked user account and assigns the role', function () {
    $organization = Organization::factory()->create();
    $user = userWithRole('hr', $organization);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/employees', [
        'organization_id' => $organization->id,
        'employee_number' => 'EMP00001',
        'first_name' => 'Aziz',
        'last_name' => 'Karimov',
        'create_account' => true,
        'username' => 'akarimov',
        'corporate_email' => 'akarimov@urtg.uz',
        'password' => 'password123',
        'role' => 'employee',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('employees', ['employee_number' => 'EMP00001']);
    $newUser = User::where('username', 'akarimov')->first();
    expect($newUser)->not->toBeNull();
    expect($newUser->hasRole('employee'))->toBeTrue();
});

it('forbids a scoped hr user from assigning the central-admin role', function () {
    $organization = Organization::factory()->create();
    $user = userWithRole('hr', $organization);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/employees', [
        'organization_id' => $organization->id,
        'employee_number' => 'EMP00002',
        'first_name' => 'Aziz',
        'last_name' => 'Karimov',
        'create_account' => true,
        'username' => 'akarimov2',
        'corporate_email' => 'akarimov2@urtg.uz',
        'password' => 'password123',
        'role' => 'central-admin',
    ])->assertStatus(422)->assertJsonValidationErrors(['role']);
});

it('forbids an hr user from creating an employee in another organization', function () {
    $ownOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $user = userWithRole('hr', $ownOrg);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/employees', [
        'organization_id' => $otherOrg->id,
        'employee_number' => 'EMP00003',
        'first_name' => 'Aziz',
        'last_name' => 'Karimov',
    ])->assertStatus(403);
});

it('rejects a duplicate employee number', function () {
    $organization = Organization::factory()->create();
    Employee::factory()->create(['organization_id' => $organization->id, 'employee_number' => 'DUP001']);
    $user = userWithRole('hr', $organization);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/employees', [
        'organization_id' => $organization->id,
        'employee_number' => 'DUP001',
        'first_name' => 'Aziz',
        'last_name' => 'Karimov',
    ])->assertStatus(422)->assertJsonValidationErrors(['employee_number']);
});

it('updates an employee and persists the change', function () {
    $organization = Organization::factory()->create();
    $employee = Employee::factory()->create(['organization_id' => $organization->id, 'phone' => '111']);
    $user = userWithRole('hr', $organization);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/employees/{$employee->id}", ['phone' => '999'])
        ->assertOk()
        ->assertJsonPath('data.phone', '999');
});

it('forbids an employee from updating their own profile through the admin endpoint', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('employee');
    $employee = Employee::factory()->create(['user_id' => $user->id, 'organization_id' => $organization->id]);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/v1/employees/{$employee->id}", ['phone' => '999'])
        ->assertStatus(403);
});

it('soft deletes an employee', function () {
    $organization = Organization::factory()->create();
    $employee = Employee::factory()->create(['organization_id' => $organization->id]);
    $user = userWithRole('central-admin', $organization);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/employees/{$employee->id}")
        ->assertOk();

    $this->assertSoftDeleted('employees', ['id' => $employee->id]);
});

it('forbids an hr user from deleting an employee', function () {
    $organization = Organization::factory()->create();
    $employee = Employee::factory()->create(['organization_id' => $organization->id]);
    $user = userWithRole('hr', $organization);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/v1/employees/{$employee->id}")
        ->assertStatus(403);
});
