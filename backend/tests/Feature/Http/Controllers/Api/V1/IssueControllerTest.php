<?php

use App\Enums\IssueStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Issue;
use App\Models\IssueActivity;
use App\Models\IssueCategory;
use App\Models\Organization;
use App\Notifications\IssueAssigned;
use App\Notifications\IssueReported;
use App\Notifications\IssueResolved;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function issuePayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Gaz quvuri shikastlangan',
        'description' => 'Quvurdan gaz hidi kelmoqda.',
        'object_name' => '3-nasos stansiyasi',
        'issue_category_id' => IssueCategory::factory()->create()->id,
        'latitude' => 41.55,
        'longitude' => 60.63,
    ], $overrides);
}

it('returns 401 when listing issues without authentication', function () {
    $this->getJson('/api/v1/issues')->assertStatus(401);
});

it('lets a department-manager report an issue for their own organization, without executors, and notifies technical-policy', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $department);
    $technicalPolicyUser = userWithRole('technical-policy', $organization);
    $category = IssueCategory::factory()->create();

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/issues', issuePayload([
        'issue_category_id' => $category->id,
    ]))->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.organization_id', $organization->id)
        ->assertJsonPath('data.department_id', $department->id)
        ->assertJsonPath('data.issue_category_id', $category->id)
        ->assertJsonCount(0, 'data.executors');

    $this->assertDatabaseHas('issue_activities', ['action' => 'created']);
    $this->assertDatabaseMissing('issue_activities', ['action' => 'assigned']);
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $technicalPolicyUser->id,
        'type' => IssueReported::class,
    ]);
});

it('lets a plain employee report an issue but ignores the executors they send, as older mobile builds do', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);
    $colleague = userWithRole('employee', $organization);

    $this->actingAs($employeeUser, 'sanctum')->postJson('/api/v1/issues', issuePayload([
        'executor_ids' => [$colleague->employee->id],
    ]))->assertCreated()->assertJsonCount(0, 'data.executors');

    $this->assertDatabaseCount('issue_executors', 0);
    Notification::assertNotSentTo($colleague, IssueAssigned::class);
});

it('stores the photos sent with a new issue as JPEGs no larger than 1600 px', function () {
    Storage::fake('local');
    $user = userWithRole('employee', Organization::factory()->create());

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/issues', issuePayload([
        'photos' => [
            UploadedFile::fake()->image('kamera.jpg', 4000, 3000)->size(5000),
            UploadedFile::fake()->image('yaqin.png', 800, 600),
        ],
    ]))->assertCreated()->assertJsonCount(2, 'data.photos');

    $issue = Issue::findOrFail($response->json('data.id'));
    $sizes = $issue->photos->map(fn ($photo) => array_slice(getimagesizefromstring(Storage::disk('local')->get($photo->path)), 0, 3));
    expect($sizes->all())->toEqual([[1600, 1200, IMAGETYPE_JPEG], [800, 600, IMAGETYPE_JPEG]]);
    expect($response->json('data.photos.0.url'))->toEndWith("/api/v1/issues/{$issue->id}/photos/{$issue->photos[0]->id}");
});

it('refuses more than five photos, or a file that is not a photo', function (array $photos) {
    Storage::fake('local');
    $user = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($user, 'sanctum')->postJson('/api/v1/issues', issuePayload(['photos' => $photos]))
        ->assertStatus(422);

    $this->assertDatabaseCount('issues', 0);
})->with([
    'six photos' => fn () => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 6)),
    'a PDF' => fn () => [UploadedFile::fake()->create('scan.pdf', 50, 'application/pdf')],
]);

it('lets every role that has an employee record report an issue', function () {
    $organization = Organization::factory()->create();

    foreach (['organization-admin', 'hr', 'safety-manager', 'manager', 'employee', 'department-manager'] as $role) {
        $user = userWithRole($role, $organization);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/issues', issuePayload([
            'organization_id' => $organization->id,
        ]))->assertCreated();
    }
});

it('lets technical-policy name several executors when reporting, notifying each of them but not the reporter', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $reporter = userWithRole('technical-policy', $organization);
    $first = userWithRole('employee', $organization);
    $second = userWithRole('department-manager', $organization);

    $response = $this->actingAs($reporter, 'sanctum')->postJson('/api/v1/issues', issuePayload([
        'organization_id' => $organization->id,
        'executor_ids' => [$first->employee->id, $second->employee->id, $reporter->employee->id],
    ]))->assertCreated()->assertJsonCount(3, 'data.executors');

    $this->assertDatabaseHas('issue_activities', ['action' => 'assigned']);
    $assigned = IssueActivity::where('action', 'assigned')->value('description');
    expect($assigned)->toContain($first->employee->fullName())->toContain($second->employee->fullName());
    Notification::assertSentTo([$first, $second], IssueAssigned::class);
    Notification::assertNotSentTo($reporter, IssueAssigned::class);
});

it('accepts an executor who has no login account', function () {
    $organization = Organization::factory()->create();
    $reporter = userWithRole('technical-policy', $organization);
    $fieldWorker = Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => null]);

    $this->actingAs($reporter, 'sanctum')->postJson('/api/v1/issues', issuePayload([
        'organization_id' => $organization->id,
        'executor_ids' => [$fieldWorker->id],
    ]))->assertCreated()->assertJsonPath('data.executors.0.id', $fieldWorker->id);
});

it('requires a category but no executors', function () {
    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);

    $this->actingAs($employeeUser, 'sanctum')->postJson('/api/v1/issues', [
        'title' => 'Nothing else',
        'latitude' => 41.55,
        'longitude' => 60.63,
    ])->assertStatus(422)
        ->assertJsonValidationErrors('issue_category_id')
        ->assertJsonMissingValidationErrors('executor_ids');
});

it('lets technical-policy report an issue and leave its executors for later', function () {
    $organization = Organization::factory()->create();
    $technicalPolicyUser = userWithRole('technical-policy', $organization);

    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/issues', issuePayload([
        'organization_id' => $organization->id,
    ]))->assertCreated()->assertJsonCount(0, 'data.executors');
});

it('rejects an inactive category', function () {
    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);

    $this->actingAs($employeeUser, 'sanctum')->postJson('/api/v1/issues', issuePayload([
        'issue_category_id' => IssueCategory::factory()->create(['status' => 'inactive'])->id,
    ]))->assertStatus(422)->assertJsonValidationErrors('issue_category_id');
});

it('rejects executors from another organization, or who are no longer employed', function () {
    $organization = Organization::factory()->create();
    $reporter = userWithRole('technical-policy', $organization);
    $outsider = userWithRole('employee', Organization::factory()->create());
    $terminated = Employee::factory()->create(['organization_id' => $organization->id, 'status' => 'terminated']);

    foreach ([[$outsider->employee->id], [$terminated->id], [$reporter->employee->id, $outsider->employee->id]] as $executors) {
        $this->actingAs($reporter, 'sanctum')->postJson('/api/v1/issues', issuePayload([
            'organization_id' => $organization->id,
            'executor_ids' => $executors,
        ]))->assertStatus(422)->assertJsonValidationErrors('executor_ids');
    }
});

it('forbids a non-central user from reporting an issue for another organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $other = userWithRole('employee', $otherOrganization);
    $employeeUser = userWithRole('employee', $organization);

    $this->actingAs($employeeUser, 'sanctum')->postJson('/api/v1/issues', issuePayload([
        'organization_id' => $otherOrganization->id,
    ]))->assertStatus(403);
});

it('lets technical-policy report an issue for the head office or any subordinate organization', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());

    foreach ([Organization::factory()->central()->create(), Organization::factory()->create()] as $organization) {
        $executor = userWithRole('employee', $organization);

        $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/issues', issuePayload([
            'organization_id' => $organization->id,
            'executor_ids' => [$executor->employee->id],
        ]))->assertCreated()->assertJsonPath('data.organization_id', $organization->id);
    }
});

it('requires a central-access user to choose the organization', function () {
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());

    $this->actingAs($technicalPolicyUser, 'sanctum')->postJson('/api/v1/issues', issuePayload())
        ->assertStatus(422)->assertJsonValidationErrors('organization_id');
});

it('offers technical-policy every active organization (head office first) and the active categories', function () {
    $subordinate = Organization::factory()->create();
    $central = Organization::factory()->central()->create();
    Organization::factory()->create(['status' => 'inactive']);
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $active = IssueCategory::factory()->create();
    IssueCategory::factory()->create(['status' => 'inactive']);

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->getJson('/api/v1/issues/options');

    $response->assertOk()
        ->assertJsonCount(1, 'data.categories')
        ->assertJsonPath('data.categories.0.id', $active->id)
        ->assertJsonPath('data.can_assign_executors', true)
        ->assertJsonPath('data.organizations.0.id', $central->id);

    expect(collect($response->json('data.organizations'))->pluck('id'))->toContain($subordinate->id);
});

it('offers a plain employee only their own organization, and tells employees and department-managers they cannot name executors', function () {
    $organization = Organization::factory()->create();
    Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $employeeUser = userWithRole('employee', $organization);
    $manager = userWithRole('department-manager', $organization, $department);

    $this->actingAs($employeeUser, 'sanctum')->getJson('/api/v1/issues/options')
        ->assertOk()
        ->assertJsonCount(1, 'data.organizations')
        ->assertJsonPath('data.organizations.0.id', $organization->id)
        ->assertJsonPath('data.can_assign_executors', false);

    $this->actingAs($manager, 'sanctum')->getJson('/api/v1/issues/options')
        ->assertOk()
        ->assertJsonPath('data.can_assign_executors', false);
});

it('lists every current employee of the chosen organization as executor candidates', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $department);
    $plain = userWithRole('employee', $organization);
    $fieldWorker = Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => null]);
    Employee::factory()->create(['organization_id' => $organization->id, 'status' => 'terminated']);
    userWithRole('employee', Organization::factory()->create());

    $response = $this->actingAs($plain, 'sanctum')
        ->getJson("/api/v1/issues/executor-candidates?organization_id={$organization->id}");

    $response->assertOk()->assertJsonCount(3, 'data');
    expect(collect($response->json('data'))->pluck('full_name')->filter()->all())->toHaveCount(3);
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toContain($manager->employee->id, $plain->employee->id, $fieldWorker->id);
});

it('does not let a non-central user list another organization\'s employees', function () {
    $employeeUser = userWithRole('employee', Organization::factory()->create());
    $other = Organization::factory()->create();

    $this->actingAs($employeeUser, 'sanctum')
        ->getJson("/api/v1/issues/executor-candidates?organization_id={$other->id}")
        ->assertStatus(403);

    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $this->actingAs($technicalPolicyUser, 'sanctum')
        ->getJson("/api/v1/issues/executor-candidates?organization_id={$other->id}")
        ->assertOk();
});

it('shows a plain employee the issues they reported or are executing, and nothing else', function () {
    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);
    $reported = Issue::factory()->create(['organization_id' => $organization->id, 'reporter_employee_id' => $employeeUser->employee->id]);
    $executing = Issue::factory()->create(['organization_id' => $organization->id]);
    $executing->executors()->attach($employeeUser->employee->id);
    $unrelated = Issue::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($employeeUser, 'sanctum')->getJson('/api/v1/issues')
        ->assertOk()->assertJsonCount(2, 'data');
    $this->actingAs($employeeUser, 'sanctum')->getJson("/api/v1/issues/{$reported->id}")->assertOk();
    $this->actingAs($employeeUser, 'sanctum')->getJson("/api/v1/issues/{$executing->id}")->assertOk();
    $this->actingAs($employeeUser, 'sanctum')->getJson("/api/v1/issues/{$unrelated->id}")->assertStatus(403);
});

it('scopes a department-manager to their department\'s issues plus any they execute', function () {
    $organization = Organization::factory()->create();
    $ownDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $otherDepartment = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $ownDepartment);
    $subordinate = userWithRole('employee', $organization, $ownDepartment);

    $own = Issue::factory()->create(['organization_id' => $organization->id, 'department_id' => $ownDepartment->id]);
    $executedByTeam = Issue::factory()->create(['organization_id' => $organization->id, 'department_id' => $otherDepartment->id]);
    $executedByTeam->executors()->attach($subordinate->employee->id);
    Issue::factory()->create(['organization_id' => $organization->id, 'department_id' => $otherDepartment->id]);

    $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/issues');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect(collect($response->json('data'))->pluck('id')->all())->toContain($own->id, $executedByTeam->id);
});

it('shows an organization-admin their whole organization\'s issues but not another organization\'s', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $mine = Issue::factory()->create(['organization_id' => $organization->id]);
    $theirs = Issue::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->actingAs($orgAdmin, 'sanctum')->getJson('/api/v1/issues')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($orgAdmin, 'sanctum')->getJson("/api/v1/issues/{$mine->id}")->assertOk();
    $this->actingAs($orgAdmin, 'sanctum')->getJson("/api/v1/issues/{$theirs->id}")->assertStatus(403);
});

it('filters the list by category, organization and executor', function () {
    $organization = Organization::factory()->create();
    $technicalPolicyUser = userWithRole('technical-policy', $organization);
    $executor = userWithRole('employee', $organization);
    $leak = IssueCategory::factory()->create();

    $match = Issue::factory()->create(['organization_id' => $organization->id, 'issue_category_id' => $leak->id]);
    $match->executors()->attach($executor->employee->id);
    Issue::factory()->create(['organization_id' => $organization->id]);

    foreach (["filter[issue_category_id]={$leak->id}", "filter[organization_id]={$organization->id}&filter[executor_id]={$executor->employee->id}"] as $query) {
        $this->actingAs($technicalPolicyUser, 'sanctum')->getJson("/api/v1/issues?{$query}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }
});

it('lets technical-policy and central-admin see every issue across organizations', function () {
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

it('lets technical-policy resolve an issue with a response and notifies the reporter and executors', function () {
    $organization = Organization::factory()->create();
    $reporterUser = userWithRole('department-manager', $organization);
    $issue = Issue::factory()->create([
        'organization_id' => $organization->id,
        'reporter_employee_id' => $reporterUser->employee->id,
    ]);
    $technicalPolicyUser = userWithRole('technical-policy', $organization);
    $executorUser = userWithRole('employee', $organization);
    $issue->executors()->attach($executorUser->employee->id);

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
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $executorUser->id,
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

it('lets central-admin resolve an issue, since it holds the issues.resolve permission', function () {
    $organization = Organization::factory()->create();
    $centralAdmin = userWithRole('central-admin', $organization);
    $issue = Issue::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($centralAdmin, 'sanctum')->postJson("/api/v1/issues/{$issue->id}/resolve", [
        'resolution_note' => 'Markaziy ofis tomonidan hal qilindi.',
    ])->assertOk();

    expect($issue->fresh()->status)->toBe(IssueStatus::Resolved);
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

it('returns 401 when assigning executors without authentication', function () {
    $issue = Issue::factory()->create();

    $this->putJson("/api/v1/issues/{$issue->id}/executors", ['executor_ids' => [1]])->assertUnauthorized();
});

it('lets technical-policy assign executors to an issue someone else reported, notifying them', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $reporter = userWithRole('employee', $organization);
    $issue = Issue::factory()->create(['organization_id' => $organization->id, 'reporter_employee_id' => $reporter->employee->id]);
    $technicalPolicyUser = userWithRole('technical-policy', Organization::factory()->create());
    $executor = userWithRole('employee', $organization);
    $fieldWorker = Employee::factory()->create(['organization_id' => $organization->id, 'user_id' => null]);

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->putJson("/api/v1/issues/{$issue->id}/executors", [
        'executor_ids' => [$executor->employee->id, $fieldWorker->id],
    ]);

    $response->assertOk()->assertJsonCount(2, 'data.executors');
    $this->assertDatabaseHas('issue_executors', ['issue_id' => $issue->id, 'employee_id' => $executor->employee->id]);
    $this->assertDatabaseHas('issue_executors', ['issue_id' => $issue->id, 'employee_id' => $fieldWorker->id]);
    $this->assertDatabaseHas('issue_activities', ['issue_id' => $issue->id, 'action' => 'assigned']);
    $this->assertDatabaseHas('audit_logs', ['module' => 'issues', 'action' => 'assigned']);
    Notification::assertSentTo($executor, IssueAssigned::class);
    Notification::assertNotSentTo($reporter, IssueAssigned::class);
});

it('replaces the executors and notifies only the newly added ones', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $issue = Issue::factory()->create(['organization_id' => $organization->id]);
    $kept = userWithRole('employee', $organization);
    $removed = userWithRole('employee', $organization);
    $added = userWithRole('employee', $organization);
    $issue->executors()->attach([$kept->employee->id, $removed->employee->id]);
    $technicalPolicyUser = userWithRole('technical-policy', $organization);

    $this->actingAs($technicalPolicyUser, 'sanctum')->putJson("/api/v1/issues/{$issue->id}/executors", [
        'executor_ids' => [$kept->employee->id, $added->employee->id],
    ])->assertOk();

    expect($issue->executors()->pluck('employees.id')->sort()->values()->all())
        ->toBe(collect([$kept->employee->id, $added->employee->id])->sort()->values()->all());
    Notification::assertSentTo($added, IssueAssigned::class);
    Notification::assertNotSentTo([$kept, $removed], IssueAssigned::class);
});

it('lets central-admin assign executors, since it holds the issues.assign permission', function () {
    $organization = Organization::factory()->create();
    $issue = Issue::factory()->create(['organization_id' => $organization->id]);
    $executor = userWithRole('employee', $organization);
    $centralAdmin = userWithRole('central-admin', Organization::factory()->create());

    $this->actingAs($centralAdmin, 'sanctum')->putJson("/api/v1/issues/{$issue->id}/executors", [
        'executor_ids' => [$executor->employee->id],
    ])->assertOk();

    $this->assertDatabaseHas('issue_executors', ['issue_id' => $issue->id, 'employee_id' => $executor->employee->id]);
});

it('forbids a department-manager from assigning executors with 403, even on an issue their department raised', function () {
    $organization = Organization::factory()->create();
    $department = Department::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('department-manager', $organization, $department);
    $issue = Issue::factory()->create(['organization_id' => $organization->id, 'department_id' => $department->id]);

    $this->actingAs($manager, 'sanctum')->putJson("/api/v1/issues/{$issue->id}/executors", [
        'executor_ids' => [$manager->employee->id],
    ])->assertForbidden();

    $this->assertDatabaseCount('issue_executors', 0);
});

it('refuses with 409 to assign executors to a resolved issue', function () {
    $organization = Organization::factory()->create();
    $issue = Issue::factory()->resolved()->create(['organization_id' => $organization->id]);
    $executor = userWithRole('employee', $organization);
    $technicalPolicyUser = userWithRole('technical-policy', $organization);

    $this->actingAs($technicalPolicyUser, 'sanctum')->putJson("/api/v1/issues/{$issue->id}/executors", [
        'executor_ids' => [$executor->employee->id],
    ])->assertConflict()->assertJsonPath('message', 'Bartaraf etilgan muammoga ijrochi tayinlab bo\'lmaydi.');

    $this->assertDatabaseCount('issue_executors', 0);
});

it('rejects with 422 an empty executor list or executors outside the issue\'s organization', function () {
    $organization = Organization::factory()->create();
    $issue = Issue::factory()->create(['organization_id' => $organization->id]);
    $outsider = userWithRole('employee', Organization::factory()->create());
    $technicalPolicyUser = userWithRole('technical-policy', $organization);

    $this->actingAs($technicalPolicyUser, 'sanctum')->putJson("/api/v1/issues/{$issue->id}/executors", ['executor_ids' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('executor_ids');
    $this->actingAs($technicalPolicyUser, 'sanctum')->putJson("/api/v1/issues/{$issue->id}/executors", ['executor_ids' => [$outsider->employee->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['executor_ids' => 'Ijrochilar muammo tegishli tashkilotning amaldagi xodimlari bo\'lishi kerak.']);

    $this->assertDatabaseCount('issue_executors', 0);
});

it('lists only issues that have no executors yet with filter[unassigned]', function () {
    $organization = Organization::factory()->create();
    $waiting = Issue::factory()->create(['organization_id' => $organization->id]);
    $assigned = Issue::factory()->create(['organization_id' => $organization->id]);
    $assigned->executors()->attach(userWithRole('employee', $organization)->employee->id);
    $technicalPolicyUser = userWithRole('technical-policy', $organization);

    $response = $this->actingAs($technicalPolicyUser, 'sanctum')->getJson('/api/v1/issues?filter[unassigned]=1');

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $waiting->id);
});
