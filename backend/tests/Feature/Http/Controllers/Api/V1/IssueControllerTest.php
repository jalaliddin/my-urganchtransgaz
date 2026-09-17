<?php

use App\Enums\IssueStatus;
use App\Models\Department;
use App\Models\Issue;
use App\Models\Organization;
use App\Notifications\IssueReported;
use App\Notifications\IssueResolved;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing issues without authentication', function () {
    $this->getJson('/api/v1/issues')->assertStatus(401);
});

it('lets a department-manager report an issue in their own department and notifies technical-policy', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $department);
    $technicalPolicyUser = userWithRole('technical-policy', $organization);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/issues', [
        'title' => 'Gaz quvuri shikastlangan',
        'description' => 'Quvurdan gaz hidi kelmoqda.',
        'object_name' => '3-nasos stansiyasi',
        'latitude' => 41.55,
        'longitude' => 60.63,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.department_id', $department->id)
        ->assertJsonPath('data.organization_id', $organization->id);

    $this->assertDatabaseHas('issue_activities', ['action' => 'created']);
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $technicalPolicyUser->id,
        'type' => IssueReported::class,
    ]);
});

it('forbids a department-manager from reporting an issue in another department', function () {
    $organization = Organization::factory()->create();
    $ownDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $otherDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $ownDepartment);

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/issues', [
        'title' => 'Cross-department issue',
        'department_id' => $otherDepartment->id,
        'latitude' => 41.55,
        'longitude' => 60.63,
    ])->assertStatus(403);
});

it('lets technical-policy report an issue against any organization/department', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());

    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/issues', [
        'title' => 'Company-wide issue',
        'organization_id' => $organization->id,
        'department_id' => $department->id,
        'latitude' => 41.55,
        'longitude' => 60.63,
    ])->assertCreated();
});

it('forbids a plain employee from reporting an issue', function () {
    $employeeUser = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($employeeUser, 'sanctum')->postJson('/api/v1/issues', [
        'title' => 'Not allowed',
        'latitude' => 41.55,
        'longitude' => 60.63,
    ])->assertStatus(403);
});

it('scopes the index to a department-manager\'s own department', function () {
    $organization = Organization::factory()->create();
    $ownDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $otherDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $ownDepartment);

    Issue::factory()->create(['organization_id' => $organization->id, 'department_id' => $ownDepartment->id]);
    Issue::factory()->create(['organization_id' => $organization->id, 'department_id' => $otherDepartment->id]);

    $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/issues');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('lets technical-policy and central-admin see every issue across departments', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    Issue::factory()->create(['organization_id' => $organizationA->id]);
    Issue::factory()->create(['organization_id' => $organizationB->id]);

    $technicalPolicyUser = userWithRole('technical-policy', $organizationA);
    $centralAdmin = userWithRole('central-admin', $organizationA);

    $this->actingAs($technicalPolicyUser, 'sanctum')->getJson('/api/v1/issues')
        ->assertOk()->assertJsonCount(2, 'data');

    $this->actingAs($centralAdmin, 'sanctum')->getJson('/api/v1/issues')
        ->assertOk()->assertJsonCount(2, 'data');
});

it('an organization A department-manager cannot view an organization B issue', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();
    $departmentA = Department::factory()->create(['organization_id' => $organizationA->id]);
    $managerA = userWithRole('department-manager', $organizationA, $departmentA);

    $issueB = Issue::factory()->create(['organization_id' => $organizationB->id]);

    $this->actingAs($managerA, 'sanctum')->getJson("/api/v1/issues/{$issueB->id}")->assertStatus(403);
});

it('lets technical-policy resolve an issue with a response and notifies the reporter', function () {
    $organization = Organization::factory()->create();
    $reporterUser = userWithRole('department-manager', $organization);
    $issue = Issue::factory()->create([
        'organization_id' => $organization->id,
        'reporter_employee_id' => $reporterUser->employee->id,
    ]);
    $technicalPolicyUser = userWithRole('technical-policy', $organization);

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->postJson("/api/v1/issues/{$issue->id}/resolve", [
        'resolution_note' => 'Quvur ta\'mirlandi.',
    ]);

    $response->assertOk()->assertJsonPath('data.status', 'resolved');

    expect($issue->fresh()->status)->toBe(IssueStatus::Resolved);
    $this->assertDatabaseHas('issue_activities', ['action' => 'resolved']);
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $reporterUser->id,
        'type' => IssueResolved::class,
    ]);
});

it('forbids a department-manager from resolving an issue, even one they can view', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $department);
    $issue = Issue::factory()->create(['organization_id' => $organization->id, 'department_id' => $department->id]);

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/issues/{$issue->id}/resolve", [
        'resolution_note' => 'Trying to resolve it myself.',
    ])->assertStatus(403);
});

it('will not resolve an issue that is already resolved', function () {
    $organization = Organization::factory()->create();
    $technicalPolicyUser = userWithRole('technical-policy', $organization);
    $issue = Issue::factory()->resolved()->create(['organization_id' => $organization->id]);

    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson("/api/v1/issues/{$issue->id}/resolve", [
        'resolution_note' => 'Again?',
    ])->assertStatus(409);
});

it('requires a resolution note to resolve an issue', function () {
    $organization = Organization::factory()->create();
    $technicalPolicyUser = userWithRole('technical-policy', $organization);
    $issue = Issue::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson("/api/v1/issues/{$issue->id}/resolve", [])
        ->assertStatus(422);
});

it('lets a department-manager comment on their own viewable issue', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $department);
    $issue = Issue::factory()->create(['organization_id' => $organization->id, 'department_id' => $department->id]);

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/issues/{$issue->id}/comments", [
        'body' => 'Ishlar boshlandimi?',
    ])->assertCreated();

    $this->assertDatabaseHas('issue_comments', ['issue_id' => $issue->id, 'body' => 'Ishlar boshlandimi?']);
});
