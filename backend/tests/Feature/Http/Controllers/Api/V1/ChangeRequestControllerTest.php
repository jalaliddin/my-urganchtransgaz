<?php

use App\Enums\ChangeRequestStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeChangeRequest;
use App\Models\Organization;
use App\Notifications\ProfileChangeRejected;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing change requests without authentication', function () {
    $this->getJson('/api/v1/change-requests')->assertStatus(401);
});

it('only shows an employee their own change requests', function () {
    $user = userWithRole('employee', Organization::factory()->create());
    EmployeeChangeRequest::factory()->create(['employee_id' => $user->employee->id]);
    EmployeeChangeRequest::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/change-requests');

    $response->assertOk()->assertJsonPath('meta.total', 1);
});

it('lets hr see the review queue across every organization', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    EmployeeChangeRequest::factory()->count(2)->create([
        'employee_id' => Employee::factory()->create(['organization_id' => Organization::factory()->create()->id])->id,
    ]);

    $response = $this->actingAs($hr, 'sanctum')->getJson('/api/v1/change-requests');

    $response->assertOk()->assertJsonPath('meta.total', 2);
});

it('applies the requested changes to the employee record on approval', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $employeeUser = userWithRole('employee', Organization::factory()->create());
    $changeRequest = EmployeeChangeRequest::factory()->create([
        'employee_id' => $employeeUser->employee->id,
        'changes' => ['first_name' => 'Approved Name'],
    ]);

    $this->actingAs($hr, 'sanctum')
        ->postJson("/api/v1/change-requests/{$changeRequest->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($employeeUser->employee->fresh()->first_name)->toBe('Approved Name');
});

it('leaves the employee record untouched when a change request is rejected', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $employeeUser = userWithRole('employee', Organization::factory()->create());
    $originalName = $employeeUser->employee->first_name;
    $changeRequest = EmployeeChangeRequest::factory()->create([
        'employee_id' => $employeeUser->employee->id,
        'changes' => ['first_name' => 'Should Not Apply'],
    ]);

    $this->actingAs($hr, 'sanctum')
        ->postJson("/api/v1/change-requests/{$changeRequest->id}/reject", ['reason' => 'Hujjat mos emas.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($employeeUser->employee->fresh()->first_name)->toBe($originalName);
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $employeeUser->id,
        'type' => ProfileChangeRejected::class,
    ]);
});

it('forbids a department-manager from reviewing a request outside their department', function () {
    $organization = Organization::factory()->create();
    $ownDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $ownDepartment);
    $otherDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $changeRequest = EmployeeChangeRequest::factory()->create([
        'employee_id' => Employee::factory()->create(['organization_id' => $organization->id, 'department_id' => $otherDepartment->id])->id,
    ]);

    $this->actingAs($manager, 'sanctum')
        ->postJson("/api/v1/change-requests/{$changeRequest->id}/approve")
        ->assertStatus(403);
});

it('will not re-review an already-approved change request', function () {
    $hr = userWithRole('hr', Organization::factory()->create());
    $changeRequest = EmployeeChangeRequest::factory()->create(['status' => ChangeRequestStatus::Approved->value]);

    $this->actingAs($hr, 'sanctum')
        ->postJson("/api/v1/change-requests/{$changeRequest->id}/approve")
        ->assertStatus(409);
});
