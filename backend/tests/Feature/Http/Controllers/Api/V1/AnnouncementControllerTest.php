<?php

use App\Enums\AnnouncementStatus;
use App\Models\Announcement;
use App\Models\AnnouncementTarget;
use App\Models\Department;
use App\Models\Organization;
use App\Notifications\AnnouncementPublished;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing announcements without authentication', function () {
    $this->getJson('/api/v1/announcements')->assertStatus(401);
});

it('forbids a plain employee from creating an announcement', function () {
    $employee = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($employee, 'sanctum')->postJson('/api/v1/announcements', [
        'title' => 'Not allowed',
        'content' => 'Body',
        'targets' => [['target_type' => 'organization', 'target_id' => $employee->employee->organization_id]],
    ])->assertStatus(403);
});

it('lets an organization-admin create a draft targeted at their own organization', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);

    $response = $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/announcements', [
        'title' => 'Ish vaqti o\'zgarishi',
        'content' => 'Ertadan boshlab ish vaqti 9:00 dan.',
        'targets' => [['target_type' => 'organization', 'target_id' => $organization->id]],
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'draft');
});

it('forbids an organization-admin from targeting a different organization', function () {
    $ownOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $ownOrganization);

    $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/announcements', [
        'title' => 'Cross-org attempt',
        'content' => 'Body',
        'targets' => [['target_type' => 'organization', 'target_id' => $otherOrganization->id]],
    ])->assertStatus(403);
});

it('forbids an organization-admin from creating an everyone-targeted announcement', function () {
    $orgAdmin = userWithRole('organization-admin', Organization::factory()->create());

    $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/announcements', [
        'title' => 'Company wide attempt',
        'content' => 'Body',
        'targets' => [['target_type' => 'everyone']],
    ])->assertStatus(403);
});

it('lets a central-admin create an everyone-targeted announcement', function () {
    $centralAdmin = userWithRole('central-admin', Organization::factory()->central()->create());

    $this->actingAs($centralAdmin, 'sanctum')->postJson('/api/v1/announcements', [
        'title' => 'Company wide',
        'content' => 'Body',
        'targets' => [['target_type' => 'everyone']],
    ])->assertCreated();
});

it('forbids the authoring organization-admin from publishing their own draft', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $announcement = Announcement::factory()->create(['author_id' => $orgAdmin->id]);
    AnnouncementTarget::factory()->organization($organization->id)->create(['announcement_id' => $announcement->id]);

    $this->actingAs($orgAdmin, 'sanctum')->postJson("/api/v1/announcements/{$announcement->id}/publish")->assertStatus(403);
});

it('lets a central-admin see drafts authored by an organization-admin, so there is something to review', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $announcement = Announcement::factory()->create(['author_id' => $orgAdmin->id]);
    AnnouncementTarget::factory()->organization($organization->id)->create(['announcement_id' => $announcement->id]);

    $centralAdmin = userWithRole('central-admin');

    $this->actingAs($centralAdmin, 'sanctum')->getJson('/api/v1/announcements')
        ->assertOk()->assertJsonPath('meta.total', 1);

    // The org-admin's own management view only shows what they authored.
    $otherOrgAdmin = userWithRole('organization-admin', Organization::factory()->create());
    $this->actingAs($otherOrgAdmin, 'sanctum')->getJson('/api/v1/announcements')
        ->assertOk()->assertJsonPath('meta.total', 0);
});

it('lets a central-admin publish a draft and notifies the targeted employee', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $targetEmployee = userWithRole('employee', $organization);

    $announcement = Announcement::factory()->create(['author_id' => $orgAdmin->id]);
    AnnouncementTarget::factory()->organization($organization->id)->create(['announcement_id' => $announcement->id]);

    $centralAdmin = userWithRole('central-admin');
    $response = $this->actingAs($centralAdmin, 'sanctum')->postJson("/api/v1/announcements/{$announcement->id}/publish");

    $response->assertOk()->assertJsonPath('data.status', 'published');
    Notification::assertSentTo($targetEmployee, AnnouncementPublished::class);
});

it('returns 409 when publishing an already-published announcement', function () {
    $announcement = Announcement::factory()->published()->create();
    AnnouncementTarget::factory()->create(['announcement_id' => $announcement->id]);
    $centralAdmin = userWithRole('central-admin');

    $this->actingAs($centralAdmin, 'sanctum')
        ->postJson("/api/v1/announcements/{$announcement->id}/publish")
        ->assertStatus(409);
});

it('archives a published announcement and blocks archiving twice', function () {
    $announcement = Announcement::factory()->published()->create();
    $centralAdmin = userWithRole('central-admin');

    $this->actingAs($centralAdmin, 'sanctum')
        ->postJson("/api/v1/announcements/{$announcement->id}/archive")
        ->assertOk()->assertJsonPath('data.status', 'archived');

    $this->actingAs($centralAdmin, 'sanctum')
        ->postJson("/api/v1/announcements/{$announcement->id}/archive")
        ->assertStatus(409);
});

it('only shows a targeted employee a published, currently-live announcement in their feed', function () {
    $organizationA = Organization::factory()->create();
    $organizationB = Organization::factory()->create();

    $announcement = Announcement::factory()->published()->create();
    AnnouncementTarget::factory()->organization($organizationA->id)->create(['announcement_id' => $announcement->id]);

    $employeeA = userWithRole('employee', $organizationA);
    $employeeB = userWithRole('employee', $organizationB);

    $this->actingAs($employeeA, 'sanctum')->getJson('/api/v1/announcements')
        ->assertOk()->assertJsonPath('meta.total', 1);

    // The mandatory cross-organization isolation check: organization B's
    // employee must not see organization A's targeted announcement.
    $this->actingAs($employeeB, 'sanctum')->getJson('/api/v1/announcements')
        ->assertOk()->assertJsonPath('meta.total', 0);

    $this->actingAs($employeeB, 'sanctum')->getJson("/api/v1/announcements/{$announcement->id}")
        ->assertStatus(403);
});

it('hides a draft announcement from anyone but its author and central roles', function () {
    $organization = Organization::factory()->create();
    $orgAdmin = userWithRole('organization-admin', $organization);
    $employee = userWithRole('employee', $organization);

    $announcement = Announcement::factory()->create(['author_id' => $orgAdmin->id]);
    AnnouncementTarget::factory()->organization($organization->id)->create(['announcement_id' => $announcement->id]);

    $this->actingAs($employee, 'sanctum')->getJson("/api/v1/announcements/{$announcement->id}")->assertStatus(403);
    $this->actingAs($orgAdmin, 'sanctum')->getJson("/api/v1/announcements/{$announcement->id}")->assertOk();
});

it('marks an announcement read when a targeted employee opens it', function () {
    $organization = Organization::factory()->create();
    $announcement = Announcement::factory()->published()->create();
    AnnouncementTarget::factory()->organization($organization->id)->create(['announcement_id' => $announcement->id]);
    $employee = userWithRole('employee', $organization);

    $this->actingAs($employee, 'sanctum')->getJson('/api/v1/announcements')
        ->assertOk()->assertJsonPath('data.0.is_read', false);

    $this->actingAs($employee, 'sanctum')->getJson("/api/v1/announcements/{$announcement->id}")->assertOk();

    $this->actingAs($employee, 'sanctum')->getJson('/api/v1/announcements')
        ->assertOk()->assertJsonPath('data.0.is_read', true);
});

it('targets employees by role regardless of organization', function () {
    $announcement = Announcement::factory()->published()->create();
    $managerRoleId = Role::where('name', 'manager')->firstOrFail()->id;
    AnnouncementTarget::factory()->role($managerRoleId)->create(['announcement_id' => $announcement->id]);

    $manager = userWithRole('manager', Organization::factory()->create());
    $employee = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($manager, 'sanctum')->getJson('/api/v1/announcements')
        ->assertOk()->assertJsonPath('meta.total', 1);

    $this->actingAs($employee, 'sanctum')->getJson('/api/v1/announcements')
        ->assertOk()->assertJsonPath('meta.total', 0);
});

it('targets only central-office employees for a central-scoped announcement', function () {
    $announcement = Announcement::factory()->published()->create();
    AnnouncementTarget::factory()->central()->create(['announcement_id' => $announcement->id]);

    $centralEmployee = userWithRole('employee', Organization::factory()->central()->create());
    $subordinateEmployee = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($centralEmployee, 'sanctum')->getJson('/api/v1/announcements')
        ->assertOk()->assertJsonPath('meta.total', 1);

    $this->actingAs($subordinateEmployee, 'sanctum')->getJson('/api/v1/announcements')
        ->assertOk()->assertJsonPath('meta.total', 0);
});

it('auto-publishes a due draft and auto-archives an expired announcement via the scheduled command', function () {
    Notification::fake();

    $organization = Organization::factory()->create();
    $employee = userWithRole('employee', $organization);

    $dueDraft = Announcement::factory()->create(['publish_at' => now()->subMinute()]);
    AnnouncementTarget::factory()->organization($organization->id)->create(['announcement_id' => $dueDraft->id]);

    $expiredPublished = Announcement::factory()->published()->create(['expire_at' => now()->subMinute()]);

    $this->artisan('announcements:process-schedule')->assertSuccessful();

    expect($dueDraft->fresh()->status)->toBe(AnnouncementStatus::Published);
    expect($expiredPublished->fresh()->status)->toBe(AnnouncementStatus::Archived);
    Notification::assertSentTo($employee, AnnouncementPublished::class);
});

it('lists roles for the target picker', function () {
    $user = userWithRole('employee', Organization::factory()->create());

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/roles');

    $response->assertOk();
    expect($response->json('data'))->not->toBeEmpty();
});

it('restricts a department target to the organization-admin\'s own organization', function () {
    $ownOrganization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $foreignDepartment = Department::factory()->create(['organization_id' => $otherOrganization->id]);
    $orgAdmin = userWithRole('organization-admin', $ownOrganization);

    $this->actingAs($orgAdmin, 'sanctum')->postJson('/api/v1/announcements', [
        'title' => 'Cross-org department attempt',
        'content' => 'Body',
        'targets' => [['target_type' => 'department', 'target_id' => $foreignDepartment->id]],
    ])->assertStatus(403);
});
