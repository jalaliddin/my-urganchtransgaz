<?php

use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Task;
use App\Notifications\TaskApproved;
use App\Notifications\TaskAssigned;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing tasks without authentication', function () {
    $this->getJson('/api/v1/tasks')->assertStatus(401);
});

it('lets a manager create a task and assigns it to an employee in their organization', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $assigneeUser = userWithRole('employee', $organization);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'title' => 'Prepare monthly report',
        'assignee_ids' => [$assigneeUser->employee->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('data.organization_id', $organization->id);

    $this->assertDatabaseHas('task_assignees', ['employee_id' => $assigneeUser->employee->id]);
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $assigneeUser->id,
        'type' => TaskAssigned::class,
    ]);
});

it('forbids a manager from assigning a task to an employee outside their organization', function () {
    $manager = userWithRole('manager', Organization::factory()->create());
    $outsider = Employee::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'title' => 'Cross-org task',
        'assignee_ids' => [$outsider->id],
    ])->assertStatus(403);
});

it('forbids a plain employee from creating a task', function () {
    $employee = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($employee, 'sanctum')->postJson('/api/v1/tasks', [
        'title' => 'Not allowed',
        'assignee_ids' => [$employee->employee->id],
    ])->assertStatus(403);
});

it('lets technical-policy assign a task across subordinate organizations', function () {
    $techPolicy = userWithRole('technical-policy', Organization::factory()->create());
    $employee = Employee::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->actingAs($techPolicy, 'sanctum')->postJson('/api/v1/tasks', [
        'title' => 'Cross-org policy task',
        'assignee_ids' => [$employee->id],
    ])->assertCreated();
});

it('forbids a manager from viewing a task outside their organization', function () {
    $manager = userWithRole('manager', Organization::factory()->create());
    $task = Task::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->actingAs($manager, 'sanctum')->getJson("/api/v1/tasks/{$task->id}")->assertStatus(403);
});

it('lets an assignee view a task even outside their manager scope', function () {
    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);
    $task = Task::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $task->assignees()->attach($employeeUser->employee->id);

    $this->actingAs($employeeUser, 'sanctum')->getJson("/api/v1/tasks/{$task->id}")->assertOk();
});

it('lets an assignee update their own progress without any manager permission', function () {
    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);
    $task = Task::factory()->create(['organization_id' => $organization->id, 'status' => TaskStatus::New->value]);
    $task->assignees()->attach($employeeUser->employee->id);

    $response = $this->actingAs($employeeUser, 'sanctum')->patchJson("/api/v1/tasks/{$task->id}/progress", ['progress' => 40]);

    $response->assertOk()->assertJsonPath('data.progress', 40)->assertJsonPath('data.status', 'in_progress');
    // Regression guard: the response must still carry assignees — an
    // un-eager-loaded relation silently disappears from the JSON (Laravel's
    // whenLoaded), which previously broke the frontend's "am I still an
    // assignee" check right after this exact action.
    $response->assertJsonCount(1, 'data.assignees');
});

it('forbids a non-assignee from updating progress', function () {
    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);
    $task = Task::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs($employeeUser, 'sanctum')->patchJson("/api/v1/tasks/{$task->id}/progress", ['progress' => 40])
        ->assertStatus(403);
});

it('lets an assignee mark a task complete, moving it to waiting', function () {
    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);
    $task = Task::factory()->create(['organization_id' => $organization->id, 'status' => TaskStatus::InProgress->value]);
    $task->assignees()->attach($employeeUser->employee->id);

    $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/complete", ['result' => 'Done.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'waiting')
        ->assertJsonPath('data.progress', 100)
        ->assertJsonCount(1, 'data.assignees');
});

it('will not complete a task that is already waiting', function () {
    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);
    $task = Task::factory()->create(['organization_id' => $organization->id, 'status' => TaskStatus::Waiting->value]);
    $task->assignees()->attach($employeeUser->employee->id);

    $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/complete")->assertStatus(409);
});

it('lets a manager approve a waiting task and notifies the assignee', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $employeeUser = userWithRole('employee', $organization);
    $task = Task::factory()->create(['organization_id' => $organization->id, 'status' => TaskStatus::Waiting->value]);
    $task->assignees()->attach($employeeUser->employee->id);

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonCount(1, 'data.assignees');

    expect($task->fresh()->completed_at)->not->toBeNull();
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $employeeUser->id,
        'type' => TaskApproved::class,
    ]);
});

it('will not approve a task that is not waiting', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $task = Task::factory()->create(['organization_id' => $organization->id, 'status' => TaskStatus::InProgress->value]);

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/approve")->assertStatus(409);
});

it('lets a manager reopen a completed task', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $task = Task::factory()->create([
        'organization_id' => $organization->id,
        'status' => TaskStatus::Completed->value,
        'completed_at' => now(),
    ]);

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/reopen")
        ->assertOk()
        ->assertJsonPath('data.status', 'in_progress');

    expect($task->fresh()->completed_at)->toBeNull();
});

it('forbids an assignee from approving their own task', function () {
    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);
    $task = Task::factory()->create(['organization_id' => $organization->id, 'status' => TaskStatus::Waiting->value]);
    $task->assignees()->attach($employeeUser->employee->id);

    $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/approve")->assertStatus(403);
});

it('lets the creator cancel their own task', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $task = Task::factory()->create(['organization_id' => $organization->id, 'creator_id' => $manager->id, 'status' => TaskStatus::New->value]);

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('will not cancel an already completed task', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $task = Task::factory()->create(['organization_id' => $organization->id, 'creator_id' => $manager->id, 'status' => TaskStatus::Completed->value]);

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/cancel")->assertStatus(409);
});

it('lets an assignee comment on their task and records an activity', function () {
    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);
    $task = Task::factory()->create(['organization_id' => $organization->id]);
    $task->assignees()->attach($employeeUser->employee->id);

    $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Working on it.'])
        ->assertCreated();

    $this->assertDatabaseHas('task_comments', ['task_id' => $task->id, 'body' => 'Working on it.']);
    $this->assertDatabaseHas('task_activities', ['task_id' => $task->id, 'action' => 'comment_added']);
});

it('forbids an outsider from commenting on a task', function () {
    $organization = Organization::factory()->create();
    $outsider = userWithRole('employee', $organization);
    $task = Task::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $this->actingAs($outsider, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Hi'])
        ->assertStatus(403);
});

it('lets a comment author delete their own comment', function () {
    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);
    $task = Task::factory()->create(['organization_id' => $organization->id]);
    $task->assignees()->attach($employeeUser->employee->id);
    $comment = $task->comments()->create(['user_id' => $employeeUser->id, 'body' => 'mine']);

    $this->actingAs($employeeUser, 'sanctum')->deleteJson("/api/v1/tasks/{$task->id}/comments/{$comment->id}")->assertOk();

    $this->assertDatabaseMissing('task_comments', ['id' => $comment->id]);
});

it('uploads and downloads a task attachment', function () {
    Storage::fake('local');

    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);
    $task = Task::factory()->create(['organization_id' => $organization->id]);
    $task->assignees()->attach($employeeUser->employee->id);

    $upload = $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/tasks/{$task->id}/attachments", [
        'file' => UploadedFile::fake()->create('report.pdf', 200, 'application/pdf'),
    ]);

    $upload->assertCreated();
    $attachmentId = $upload->json('data.id');

    $this->actingAs($employeeUser, 'sanctum')
        ->get("/api/v1/tasks/{$task->id}/attachments/{$attachmentId}/download")
        ->assertOk();

    $this->assertDatabaseHas('task_activities', ['task_id' => $task->id, 'action' => 'file_uploaded']);
});
