<?php

use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

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

it('lets an hr user create an employee in a different organization, since hr is company-wide', function () {
    $ownOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $user = userWithRole('hr', $ownOrg);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/employees', [
        'organization_id' => $otherOrg->id,
        'employee_number' => 'EMP00003',
        'first_name' => 'Aziz',
        'last_name' => 'Karimov',
    ])->assertCreated();
});

it('forbids an organization-admin from creating an employee in another organization', function () {
    $ownOrg = Organization::factory()->create();
    $otherOrg = Organization::factory()->create();
    $user = userWithRole('organization-admin', $ownOrg);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/employees', [
        'organization_id' => $otherOrg->id,
        'employee_number' => 'EMP00003',
        'first_name' => 'Aziz',
        'last_name' => 'Karimov',
    ])->assertStatus(403);
});

it('lets hr change an employee\'s role and audit-logs it as permission_changed', function () {
    $organization = Organization::factory()->create();
    $hr = userWithRole('hr', $organization);
    $employeeUser = userWithRole('employee', $organization);

    $response = $this->actingAs($hr, 'sanctum')
        ->putJson("/api/v1/employees/{$employeeUser->employee->id}/role", ['role' => 'manager']);

    $response->assertOk();
    expect($employeeUser->fresh()->hasRole('manager'))->toBeTrue();
    expect($employeeUser->fresh()->hasRole('employee'))->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'permission_changed',
        'module' => 'users',
        'entity_id' => $employeeUser->id,
    ]);
});

it('forbids a scoped hr user from promoting an employee to central-admin via a role change', function () {
    $organization = Organization::factory()->create();
    $hr = userWithRole('hr', $organization);
    $employeeUser = userWithRole('employee', $organization);

    $this->actingAs($hr, 'sanctum')
        ->putJson("/api/v1/employees/{$employeeUser->employee->id}/role", ['role' => 'central-admin'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
});

it('lets an organization-admin change the role of an employee in their own organization', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $employeeUser = userWithRole('employee', $organization);

    $this->actingAs($orgAdmin, 'sanctum')
        ->putJson("/api/v1/employees/{$employeeUser->employee->id}/role", ['role' => 'manager'])
        ->assertOk();

    expect($employeeUser->fresh()->hasRole('manager'))->toBeTrue();
});

it('forbids an organization-admin from changing the role of an employee in another organization', function () {
    $orgAdmin = userWithRole('organization-admin', Organization::factory()->create());
    $employeeUser = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($orgAdmin, 'sanctum')
        ->putJson("/api/v1/employees/{$employeeUser->employee->id}/role", ['role' => 'manager'])
        ->assertStatus(403);

    expect($employeeUser->fresh()->hasRole('employee'))->toBeTrue();
});

it('forbids an organization-admin from granting a central-access role (hr, technical-policy) via a role change', function (string $centralRole) {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $employeeUser = userWithRole('employee', $organization);

    $this->actingAs($orgAdmin, 'sanctum')
        ->putJson("/api/v1/employees/{$employeeUser->employee->id}/role", ['role' => $centralRole])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['role']);
})->with(['hr', 'technical-policy']);

it('forbids anyone from changing their own role', function () {
    $organization = Organization::factory()->create();
    $hr = userWithRole('hr', $organization);

    $this->actingAs($hr, 'sanctum')
        ->putJson("/api/v1/employees/{$hr->employee->id}/role", ['role' => 'employee'])
        ->assertStatus(403);

    expect($hr->fresh()->hasRole('hr'))->toBeTrue();
});

it('forbids an organization-admin from changing the role of a peer organization-admin', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $peer = userWithRole('organization-admin', $organization);

    $this->actingAs($orgAdmin, 'sanctum')
        ->putJson("/api/v1/employees/{$peer->employee->id}/role", ['role' => 'employee'])
        ->assertStatus(403);

    expect($peer->fresh()->hasRole('organization-admin'))->toBeTrue();
});

it('lets hr open an account for an employee who was created without one', function () {
    $organization = Organization::factory()->create();
    $hr = userWithRole('hr', $organization);
    $employee = Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => null]);

    $response = $this->actingAs($hr, 'sanctum')->postJson("/api/v1/employees/{$employee->id}/account", [
        'username' => 'newlogin',
        'corporate_email' => 'newlogin@urtg.uz',
        'password' => 'password123',
        'role' => 'employee',
    ]);

    $response->assertCreated();

    $employee->refresh();
    expect($employee->user_id)->not->toBeNull();
    $newUser = User::find($employee->user_id);
    expect($newUser->username)->toBe('newlogin');
    expect($newUser->hasRole('employee'))->toBeTrue();
});

it('rejects opening a second account for an employee who already has one', function () {
    $organization = Organization::factory()->create();
    $hr = userWithRole('hr', $organization);
    $employeeUser = userWithRole('employee', $organization);

    $this->actingAs($hr, 'sanctum')->postJson("/api/v1/employees/{$employeeUser->employee->id}/account", [
        'username' => 'another',
        'password' => 'password123',
        'role' => 'employee',
    ])->assertStatus(409);
});

it('forbids a scoped hr user from opening a central-admin account via the account-creation endpoint', function () {
    $organization = Organization::factory()->create();
    $hr = userWithRole('hr', $organization);
    $employee = Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => null]);

    $this->actingAs($hr, 'sanctum')->postJson("/api/v1/employees/{$employee->id}/account", [
        'username' => 'newlogin',
        'password' => 'password123',
        'role' => 'central-admin',
    ])->assertStatus(422)->assertJsonValidationErrors(['role']);
});

it('lets an organization-admin open an account for an employee in their own organization', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $employee = Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => null]);

    $this->actingAs($orgAdmin, 'sanctum')->postJson("/api/v1/employees/{$employee->id}/account", [
        'username' => 'newlogin',
        'password' => 'password123',
        'role' => 'employee',
    ])->assertCreated();

    expect(User::find($employee->fresh()->user_id)->hasRole('employee'))->toBeTrue();
});

it('forbids an organization-admin from opening an account for an employee in another organization', function () {
    $orgAdmin = userWithRole('organization-admin', Organization::factory()->create());
    $employee = Employee::factory()->create(['organization_id' => Organization::factory()->create()->id, 'user_id' => null]);

    $this->actingAs($orgAdmin, 'sanctum')->postJson("/api/v1/employees/{$employee->id}/account", [
        'username' => 'newlogin',
        'password' => 'password123',
        'role' => 'employee',
    ])->assertStatus(403);

    expect($employee->fresh()->user_id)->toBeNull();
});

it('forbids an organization-admin from opening an account with a central-access role (hr, technical-policy)', function (string $centralRole) {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $employee = Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => null]);

    $this->actingAs($orgAdmin, 'sanctum')->postJson("/api/v1/employees/{$employee->id}/account", [
        'username' => 'newlogin',
        'password' => 'password123',
        'role' => $centralRole,
    ])->assertStatus(422)->assertJsonValidationErrors(['role']);

    expect($employee->fresh()->user_id)->toBeNull();
})->with(['hr', 'technical-policy']);

it('forbids an organization-admin from creating a new employee account with a central-access role (hr, technical-policy)', function (string $centralRole) {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);

    $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/employees', [
        'organization_id' => $organization->id,
        'employee_number' => 'EMP00099',
        'first_name' => 'Aziz',
        'last_name' => 'Karimov',
        'create_account' => true,
        'username' => 'newlogin',
        'corporate_email' => 'newlogin@urtg.uz',
        'password' => 'password123',
        'role' => $centralRole,
    ])->assertStatus(422)->assertJsonValidationErrors(['role']);

    $this->assertDatabaseMissing('users', ['username' => 'newlogin']);
})->with(['hr', 'technical-policy']);

it('lets hr reset an employee\'s password', function () {
    $organization = Organization::factory()->create();
    $hr = userWithRole('hr', $organization);
    $employeeUser = userWithRole('employee', $organization);

    $this->actingAs($hr, 'sanctum')->putJson("/api/v1/employees/{$employeeUser->employee->id}/password", [
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertOk();

    expect(Hash::check('newpassword123', $employeeUser->fresh()->password))->toBeTrue();
});

it('rejects resetting the password of an employee with no linked account', function () {
    $organization = Organization::factory()->create();
    $hr = userWithRole('hr', $organization);
    $employee = Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => null]);

    $this->actingAs($hr, 'sanctum')->putJson("/api/v1/employees/{$employee->id}/password", [
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertStatus(404);
});

it('rejects a password reset without confirmation', function () {
    $organization = Organization::factory()->create();
    $hr = userWithRole('hr', $organization);
    $employeeUser = userWithRole('employee', $organization);

    $this->actingAs($hr, 'sanctum')->putJson("/api/v1/employees/{$employeeUser->employee->id}/password", [
        'password' => 'newpassword123',
    ])->assertStatus(422)->assertJsonValidationErrors(['password']);
});

it('lets an organization-admin reset the password of an employee in their own organization', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $employeeUser = userWithRole('employee', $organization);

    $this->actingAs($orgAdmin, 'sanctum')->putJson("/api/v1/employees/{$employeeUser->employee->id}/password", [
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertOk();

    expect(Hash::check('newpassword123', $employeeUser->fresh()->password))->toBeTrue();
});

it('forbids an organization-admin from resetting the password of an employee in another organization', function () {
    $orgAdmin = userWithRole('organization-admin', Organization::factory()->create());
    $employeeUser = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($orgAdmin, 'sanctum')->putJson("/api/v1/employees/{$employeeUser->employee->id}/password", [
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertStatus(403);

    expect(Hash::check('newpassword123', $employeeUser->fresh()->password))->toBeFalse();
});

it('forbids a password reset that would take over a more privileged account', function (string $actorRole, string $targetRole) {
    $organization = Organization::factory()->create();
    $actor = userWithRole($actorRole, $organization);
    $target = userWithRole($targetRole, $organization);

    $this->actingAs($actor, 'sanctum')->putJson("/api/v1/employees/{$target->employee->id}/password", [
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertStatus(403);

    expect(Hash::check('newpassword123', $target->fresh()->password))->toBeFalse();
})->with([
    'hr taking over central-admin' => ['hr', 'central-admin'],
    'hr taking over super-admin' => ['hr', 'super-admin'],
    'organization-admin taking over a peer organization-admin' => ['organization-admin', 'organization-admin'],
    'organization-admin taking over hr' => ['organization-admin', 'hr'],
]);

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

it('creates an employee with a Dahua terminal person id', function () {
    $organization = Organization::factory()->create();
    $user = userWithRole('hr', $organization);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/employees', [
        'organization_id' => $organization->id,
        'employee_number' => 'EMP00004',
        'dahua_person_id' => '1001',
        'first_name' => 'Aziz',
        'last_name' => 'Karimov',
    ])->assertCreated();

    $this->assertDatabaseHas('employees', ['employee_number' => 'EMP00004', 'dahua_person_id' => '1001']);
});

it('rejects a Dahua person id already assigned to another employee', function () {
    $organization = Organization::factory()->create();
    Employee::factory()->create(['organization_id' => $organization->id, 'dahua_person_id' => '1001']);
    $user = userWithRole('hr', $organization);

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/employees', [
        'organization_id' => $organization->id,
        'employee_number' => 'EMP00005',
        'dahua_person_id' => '1001',
        'first_name' => 'Aziz',
        'last_name' => 'Karimov',
    ])->assertStatus(422)->assertJsonValidationErrors(['dahua_person_id']);
});

it('lets an employee keep their own Dahua person id when updating other fields', function () {
    $organization = Organization::factory()->create();
    $employee = Employee::factory()->create(['organization_id' => $organization->id, 'dahua_person_id' => '1001']);
    $user = userWithRole('hr', $organization);

    $this->actingAs($user, 'sanctum')->putJson("/api/v1/employees/{$employee->id}", [
        'dahua_person_id' => '1001',
        'phone' => '999',
    ])->assertOk();

    expect($employee->fresh()->dahua_person_id)->toBe('1001');
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

it('exports employees as csv, unwrapping the status enum instead of failing on it', function () {
    $organization = Organization::factory()->create();
    Employee::factory()->create(['organization_id' => $organization->id, 'status' => 'active']);
    $user = userWithRole('central-admin', $organization);

    $response = $this->actingAs($user, 'sanctum')->get('/api/v1/employees?export=csv');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('active');
});

it('exports employees as xlsx, unwrapping the status enum instead of failing on it', function () {
    $organization = Organization::factory()->create();
    Employee::factory()->create(['organization_id' => $organization->id, 'status' => 'active']);
    $user = userWithRole('central-admin', $organization);

    $response = $this->actingAs($user, 'sanctum')->get('/api/v1/employees?export=xlsx');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))
        ->toContain('officedocument.spreadsheetml.sheet');
});

it('exports employees as pdf', function () {
    $organization = Organization::factory()->create();
    Employee::factory()->create(['organization_id' => $organization->id]);
    $user = userWithRole('central-admin', $organization);

    $response = $this->actingAs($user, 'sanctum')->get('/api/v1/employees?export=pdf');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

it('finds employees by last name or personnel number, not just first name', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $byLastName = Employee::factory()->create(['last_name' => 'Saidov', 'first_name' => 'Jamshid']);
    $byNumber = Employee::factory()->create(['employee_number' => 'T-90417']);
    Employee::factory()->create(['last_name' => 'Karimov', 'first_name' => 'Bekzod', 'employee_number' => 'T-11111']);

    $this->actingAs($hr, 'sanctum')->getJson('/api/v1/employees?filter[search]=Saidov')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $byLastName->id);

    $this->actingAs($hr, 'sanctum')->getJson('/api/v1/employees?filter[search]=90417')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $byNumber->id);
});
