<?php

use App\Enums\IssueStatus;
use App\Models\Department;
use App\Models\Issue;
use App\Models\IssueCategory;
use App\Models\Organization;
use App\Notifications\IssueAssigned;
use App\Notifications\IssueReported;
use App\Notifications\IssueResolved;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

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

    $category = IssueCategory::factory()->create();

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/issues', [
        'title' => 'Gaz quvuri shikastlangan',
        'description' => 'Quvurdan gaz hidi kelmoqda.',
        'object_name' => '3-nasos stansiyasi',
        'issue_category_id' => $category->id,
        'latitude' => 41.55,
        'longitude' => 60.63,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.department_id', $department->id)
        ->assertJsonPath('data.organization_id', $organization->id)
        ->assertJsonPath('data.issue_category_id', $category->id)
        ->assertJsonPath('data.responsible_employee_id', $manager->employee->id);

    $this->assertDatabaseHas('issue_activities', ['action' => 'created']);
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $technicalPolicyUser->id,
        'type' => IssueReported::class,
    ]);
});

it('forbids a department-manager from reporting an issue for another organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $manager = userWithRole('department-manager', $organization, Department::factory()->create(['organization_id' => $organization->id]));

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/issues', [
        'title' => 'Cross-organization issue',
        'organization_id' => $otherOrganization->id,
        'issue_category_id' => IssueCategory::factory()->create()->id,
        'latitude' => 41.55,
        'longitude' => 60.63,
    ])->assertStatus(403);
});

it('always makes a department-manager responsible for their own issue, ignoring any responsible they send', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $department);
    $colleague = userWithRole('department-manager', $organization, $department);
    Notification::fake();

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/issues', [
        'title' => 'Mine',
        'issue_category_id' => IssueCategory::factory()->create()->id,
        'responsible_employee_id' => $colleague->employee->id,
        'latitude' => 41.55,
        'longitude' => 60.63,
    ])->assertCreated()->assertJsonPath('data.responsible_employee_id', $manager->employee->id);

    Notification::assertNotSentTo([$manager, $colleague], IssueAssigned::class);
});

it('requires a category when reporting an issue', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('department-manager', $organization, Department::factory()->create(['organization_id' => $organization->id]));

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/issues', [
        'title' => 'No category',
        'latitude' => 41.55,
        'longitude' => 60.63,
    ])->assertStatus(422)->assertJsonValidationErrors('issue_category_id');
});

it('rejects an inactive category', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('department-manager', $organization, Department::factory()->create(['organization_id' => $organization->id]));
    $category = IssueCategory::factory()->create(['status' => 'inactive']);

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/issues', [
        'title' => 'Retired category',
        'issue_category_id' => $category->id,
        'latitude' => 41.55,
        'longitude' => 60.63,
    ])->assertStatus(422)->assertJsonValidationErrors('issue_category_id');
});

it('lets technical-policy report an issue for a subordinate organization, naming a responsible employee who is notified', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $responsibleUser = userWithRole('department-manager', $organization, $department);
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $category = IssueCategory::factory()->create();

    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/issues', [
        'title' => 'Company-wide issue',
        'organization_id' => $organization->id,
        'issue_category_id' => $category->id,
        'responsible_employee_id' => $responsibleUser->employee->id,
        'latitude' => 41.55,
        'longitude' => 60.63,
    ])->assertCreated()
        ->assertJsonPath('data.organization_id', $organization->id)
        ->assertJsonPath('data.department_id', $department->id)
        ->assertJsonPath('data.responsible_employee_id', $responsibleUser->employee->id);

    $this->assertDatabaseHas('issue_activities', ['action' => 'assigned']);
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $responsibleUser->id,
        'type' => IssueAssigned::class,
    ]);
});

it('requires technical-policy to name a responsible employee and an organization', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());

    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/issues', [
        'title' => 'Nobody responsible',
        'issue_category_id' => IssueCategory::factory()->create()->id,
        'latitude' => 41.55,
        'longitude' => 60.63,
    ])->assertStatus(422)->assertJsonValidationErrors(['organization_id', 'responsible_employee_id']);
});

it('rejects a responsible employee from a different organization or without issue access', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $category = IssueCategory::factory()->create();

    $outsider = userWithRole('department-manager', $otherOrganization);
    $plainEmployee = userWithRole('employee', $organization);

    foreach ([$outsider, $plainEmployee] as $candidate) {
        $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/issues', [
            'title' => 'Wrong person',
            'organization_id' => $organization->id,
            'issue_category_id' => $category->id,
            'responsible_employee_id' => $candidate->employee->id,
            'latitude' => 41.55,
            'longitude' => 60.63,
        ])->assertStatus(422)->assertJsonValidationErrors('responsible_employee_id');
    }
});

it('offers only subordinate organizations, active categories, and the choose-responsible flag to technical-policy', function () {
    $subordinate = Organization::factory()->create();
    Organization::factory()->central()->create();
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $active = IssueCategory::factory()->create();
    IssueCategory::factory()->create(['status' => 'inactive']);

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->getJson('/api/v1/issues/options');

    $response->assertOk()
        ->assertJsonPath('data.must_choose_responsible', true)
        ->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.categories.0.id', $active->id);

    $ids = collect($response->json('data.organizations'))->pluck('id');
    expect($ids)->toContain($subordinate->id);
    expect($ids)->each->not->toBe(Organization::where('type', 'central')->value('id'));
});

it('offers a department-manager only their own organization and no responsible choice', function () {
    $organization = Organization::factory()->create();
    Organization::factory()->create();
    $manager = userWithRole('department-manager', $organization, Department::factory()->create(['organization_id' => $organization->id]));

    $this->actingAs($manager, 'sanctum')->getJson('/api/v1/issues/options')
        ->assertOk()
        ->assertJsonPath('data.must_choose_responsible', false)
        ->assertJsonCount(1, 'data.organizations')
        ->assertJsonPath('data.organizations.0.id', $organization->id);
});

it('lists only eligible responsible candidates for the chosen organization', function () {
    $organization = Organization::factory()->create();
    $eligible = userWithRole('department-manager', $organization, Department::factory()->create(['organization_id' => $organization->id]));
    userWithRole('employee', $organization);
    userWithRole('department-manager', Organization::factory()->create());
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')
        ->getJson("/api/v1/issues/responsible-candidates?organization_id={$organization->id}");

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $eligible->employee->id);
});

it('does not offer responsible candidates to a department-manager', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('department-manager', $organization, Department::factory()->create(['organization_id' => $organization->id]));

    $this->actingAs($manager, 'sanctum')
        ->getJson("/api/v1/issues/responsible-candidates?organization_id={$organization->id}")
        ->assertStatus(403);
});

it('lets the responsible employee see their issue, and filters the list by category', function () {
    $organization = Organization::factory()->create();
    $ownDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $otherDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $ownDepartment);
    $leak = IssueCategory::factory()->create();
    $damage = IssueCategory::factory()->create();

    $assigned = Issue::factory()->create([
        'organization_id' => $organization->id,
        'department_id' => $otherDepartment->id,
        'responsible_employee_id' => $manager->employee->id,
        'issue_category_id' => $leak->id,
    ]);
    Issue::factory()->create(['organization_id' => $organization->id, 'department_id' => $ownDepartment->id, 'issue_category_id' => $damage->id]);
    Issue::factory()->create(['organization_id' => $organization->id, 'department_id' => $otherDepartment->id]);

    $this->actingAs($manager, 'sanctum')->getJson('/api/v1/issues')->assertOk()->assertJsonCount(2, 'data');
    $this->actingAs($manager, 'sanctum')->getJson("/api/v1/issues/{$assigned->id}")->assertOk();
    $this->actingAs($manager, 'sanctum')->getJson("/api/v1/issues?filter[issue_category_id]={$leak->id}")
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $assigned->id);
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
