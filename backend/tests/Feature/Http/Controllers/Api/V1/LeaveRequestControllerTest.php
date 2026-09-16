<?php

use App\Enums\EmployeeStatus;
use App\Models\BusinessTrip;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Models\Organization;
use App\Notifications\LeaveRequestApproved;
use App\Notifications\LeaveRequestDepartmentApproved;
use App\Notifications\LeaveRequestRejected;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing leave requests without authentication', function () {
    $this->getJson('/api/v1/leave-requests')->assertStatus(401);
});

it('lets an employee submit a leave request, starting at pending when their department has a manager', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $deptManager = userWithRole('department-manager', $organization, $department);
    $department->update(['manager_id' => $deptManager->employee->id]);

    $employee = userWithRole('employee', $organization, $department);

    $response = $this->actingAs($employee, 'sanctum')->postJson('/api/v1/leave-requests', [
        'type' => 'vacation',
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
        'reason' => 'Yillik ta\'til',
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'pending');
});

it('starts a request at department_approved when the employee\'s department has no manager', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id, 'manager_id' => null]);
    $employee = userWithRole('employee', $organization, $department);

    $response = $this->actingAs($employee, 'sanctum')->postJson('/api/v1/leave-requests', [
        'type' => 'sick_leave',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'department_approved');
});

it('runs the full two-stage approval and flips Employee.status when the range covers today', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $deptManager = userWithRole('department-manager', $organization, $department);
    $department->update(['manager_id' => $deptManager->employee->id]);
    $employee = userWithRole('employee', $organization, $department);

    $leaveRequest = LeaveRequest::factory()->create([
        'employee_id' => $employee->employee->id,
        'type' => 'vacation',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(3)->toDateString(),
    ]);

    $this->actingAs($deptManager, 'sanctum')
        ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/department-approve")
        ->assertOk()->assertJsonPath('data.status', 'department_approved');

    Notification::assertSentTo($employee, LeaveRequestDepartmentApproved::class);

    $hr = userWithRole('hr');
    $this->actingAs($hr, 'sanctum')
        ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/approve")
        ->assertOk()->assertJsonPath('data.status', 'approved');

    Notification::assertSentTo($employee, LeaveRequestApproved::class);
    expect($employee->employee->fresh()->status)->toBe(EmployeeStatus::Vacation);
});

it('does not flip Employee.status for a future-dated approval — the scheduled command handles that', function () {
    $organization = Organization::factory()->create();
    $employee = userWithRole('employee', $organization);

    $leaveRequest = LeaveRequest::factory()->departmentApproved()->create([
        'employee_id' => $employee->employee->id,
        'type' => 'vacation',
        'start_date' => now()->addDays(5)->toDateString(),
        'end_date' => now()->addDays(10)->toDateString(),
    ]);

    $orgAdmin = userWithRole('organization-admin', $organization);
    $this->actingAs($orgAdmin, 'sanctum')
        ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/approve")
        ->assertOk();

    expect($employee->employee->fresh()->status)->toBe(EmployeeStatus::Active);
});

it('forbids a department-manager from a different department from approving stage 1', function () {
    $organization = Organization::factory()->create();
    $deptA = Department::factory()->create(['organization_id' => $organization->id]);
    $deptB = Department::factory()->create(['organization_id' => $organization->id]);
    $managerB = userWithRole('department-manager', $organization, $deptB);
    $employeeA = userWithRole('employee', $organization, $deptA);

    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employeeA->employee->id]);

    $this->actingAs($managerB, 'sanctum')
        ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/department-approve")
        ->assertStatus(403);
});

it('forbids an organization-B HR/admin from acting on an organization-A request — the mandatory cross-organization isolation test', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $employeeA = userWithRole('employee', $organizationA);
    $orgAdminB = userWithRole('organization-admin', $organizationB);

    $leaveRequest = LeaveRequest::factory()->departmentApproved()->create(['employee_id' => $employeeA->employee->id]);

    $this->actingAs($orgAdminB, 'sanctum')
        ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/approve")
        ->assertStatus(403);

    $this->actingAs($orgAdminB, 'sanctum')
        ->getJson('/api/v1/leave-requests')
        ->assertOk()->assertJsonPath('meta.total', 0);
});

it('rejects a request at stage 1 and notifies the employee, terminal — HR can no longer act on it', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $deptManager = userWithRole('department-manager', $organization, $department);
    $department->update(['manager_id' => $deptManager->employee->id]);
    $employee = userWithRole('employee', $organization, $department);

    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->employee->id]);

    $this->actingAs($deptManager, 'sanctum')
        ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/department-reject", ['reason' => 'Ishlar band'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');

    Notification::assertSentTo($employee, LeaveRequestRejected::class);

    $hr = userWithRole('hr');
    $this->actingAs($hr, 'sanctum')
        ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/approve")
        ->assertStatus(403);
});

it('lets an employee cancel their own pending request but not once approved', function () {
    $organization = Organization::factory()->create();
    $employee = userWithRole('employee', $organization);
    $leaveRequest = LeaveRequest::factory()->create(['employee_id' => $employee->employee->id]);

    $this->actingAs($employee, 'sanctum')
        ->postJson("/api/v1/leave-requests/{$leaveRequest->id}/cancel")
        ->assertOk()->assertJsonPath('data.status', 'cancelled');

    $approvedRequest = LeaveRequest::factory()->approved()->create(['employee_id' => $employee->employee->id]);
    $this->actingAs($employee, 'sanctum')
        ->postJson("/api/v1/leave-requests/{$approvedRequest->id}/cancel")
        ->assertStatus(403);
});

it('syncs Employee.status from approved requests and business trips via the scheduled command, reverting once the range passes', function () {
    $organization = Organization::factory()->create();

    $onVacationNow = userWithRole('employee', $organization);
    LeaveRequest::factory()->approved()->create([
        'employee_id' => $onVacationNow->employee->id,
        'type' => 'vacation',
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
    ]);

    $onBusinessTripNow = userWithRole('employee', $organization);
    BusinessTrip::factory()->create([
        'employee_id' => $onBusinessTripNow->employee->id,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
    ]);

    $backFromVacation = userWithRole('employee', $organization);
    $backFromVacation->employee->update(['status' => EmployeeStatus::Vacation]);
    LeaveRequest::factory()->approved()->create([
        'employee_id' => $backFromVacation->employee->id,
        'type' => 'vacation',
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDays(2)->toDateString(),
    ]);

    $this->artisan('leave:sync-employee-status')->assertSuccessful();

    expect($onVacationNow->employee->fresh()->status)->toBe(EmployeeStatus::Vacation);
    expect($onBusinessTripNow->employee->fresh()->status)->toBe(EmployeeStatus::BusinessTrip);
    expect($backFromVacation->employee->fresh()->status)->toBe(EmployeeStatus::Active);
});

it('includes the caller\'s own requests alongside the review queue for a department-manager', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $deptManager = userWithRole('department-manager', $organization, $department);
    $department->update(['manager_id' => $deptManager->employee->id]);

    LeaveRequest::factory()->create(['employee_id' => $deptManager->employee->id]);
    $employee = userWithRole('employee', $organization, $department);
    LeaveRequest::factory()->create(['employee_id' => $employee->employee->id]);

    $response = $this->actingAs($deptManager, 'sanctum')->getJson('/api/v1/leave-requests');

    $response->assertOk()->assertJsonPath('meta.total', 2);
});
