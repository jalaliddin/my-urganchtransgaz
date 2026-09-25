<?php

use App\Enums\TaskStatus;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Task;
use App\Models\TaskCategory;
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

it('creates a task in an active category and returns that category', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $assigneeUser = userWithRole('employee', $organization);
    $category = TaskCategory::factory()->create(['name' => 'Ta\'mirlash ishlari']);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'title' => 'Quvurni ta\'mirlash',
        'task_category_id' => $category->id,
        'assignee_ids' => [$assigneeUser->employee->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.task_category_id', $category->id)
        ->assertJsonPath('data.category.name', 'Ta\'mirlash ishlari');
    $this->assertDatabaseHas('tasks', ['title' => 'Quvurni ta\'mirlash', 'task_category_id' => $category->id]);
});

it('rejects an inactive category when creating a task with 422', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $assigneeUser = userWithRole('employee', $organization);
    $inactive = TaskCategory::factory()->inactive()->create();

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'title' => 'Eski kategoriyada',
        'task_category_id' => $inactive->id,
        'assignee_ids' => [$assigneeUser->employee->id],
    ])->assertUnprocessable()->assertJsonValidationErrors('task_category_id');

    $this->assertDatabaseMissing('tasks', ['title' => 'Eski kategoriyada']);
});

it('lets an edited task keep a category that was deactivated after it was assigned', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $deactivatedSince = TaskCategory::factory()->inactive()->create();
    $task = Task::factory()->create([
        'creator_id' => $manager->id,
        'organization_id' => $organization->id,
        'task_category_id' => $deactivatedSince->id,
    ]);

    $this->actingAs($manager, 'sanctum')->putJson("/api/v1/tasks/{$task->id}", [
        'title' => 'Yangilangan sarlavha',
        'task_category_id' => $deactivatedSince->id,
    ])->assertOk();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Yangilangan sarlavha', 'task_category_id' => $deactivatedSince->id]);
});

it('rejects switching an edited task to a different inactive category with 422', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $current = TaskCategory::factory()->create();
    $otherInactive = TaskCategory::factory()->inactive()->create();
    $task = Task::factory()->create([
        'creator_id' => $manager->id,
        'organization_id' => $organization->id,
        'task_category_id' => $current->id,
    ]);

    $this->actingAs($manager, 'sanctum')->putJson("/api/v1/tasks/{$task->id}", ['task_category_id' => $otherInactive->id])
        ->assertUnprocessable()->assertJsonValidationErrors('task_category_id');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_category_id' => $current->id]);
});

it('filters the task list by category', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $repair = TaskCategory::factory()->create();
    $inCategory = Task::factory()->create(['organization_id' => $organization->id, 'task_category_id' => $repair->id]);
    Task::factory()->create(['organization_id' => $organization->id]);

    $response = $this->actingAs($manager, 'sanctum')->getJson("/api/v1/tasks?filter[task_category_id]={$repair->id}");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $inCategory->id)
        ->assertJsonPath('data.0.category.id', $repair->id);
});

it('searches the task list by title', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $report = Task::factory()->create(['organization_id' => $organization->id, 'title' => 'Oylik hisobotni tayyorlash']);
    Task::factory()->create(['organization_id' => $organization->id, 'title' => 'Quvurni tekshirish']);

    $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/tasks?filter[search]=hisobot');

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $report->id);
});

it('narrows the list to tasks assigned to the current user with filter[mine]=assigned', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $assignedToMe = Task::factory()->create(['organization_id' => $organization->id]);
    $assignedToMe->assignees()->attach($manager->employee->id);
    Task::factory()->create(['organization_id' => $organization->id, 'creator_id' => $manager->id]);

    $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/tasks?filter[mine]=assigned');

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $assignedToMe->id);
});

it('narrows the list to tasks the current user created with filter[mine]=created', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $createdByMe = Task::factory()->create(['organization_id' => $organization->id, 'creator_id' => $manager->id]);
    Task::factory()->create(['organization_id' => $organization->id]);

    $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/tasks?filter[mine]=created');

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $createdByMe->id);
});

it('counts visible tasks per status for the list tabs, leaving out other organizations', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    Task::factory()->count(2)->create(['organization_id' => $organization->id]);
    Task::factory()->create(['organization_id' => $organization->id, 'status' => TaskStatus::Completed]);
    Task::factory()->create(['organization_id' => Organization::factory()->create()->id]);

    $response = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/tasks/summary');

    $response->assertOk()
        ->assertJsonPath('data.total', 3)
        ->assertJsonPath('data.by_status.new', 2)
        ->assertJsonPath('data.by_status.completed', 1)
        ->assertJsonPath('data.by_status.overdue', 0);
});

it('applies the list filters to the status counts', function () {
    $organization = Organization::factory()->create();
    $manager = userWithRole('manager', $organization);
    $repair = TaskCategory::factory()->create();
    Task::factory()->create(['organization_id' => $organization->id, 'task_category_id' => $repair->id]);
    Task::factory()->count(2)->create(['organization_id' => $organization->id]);

    $response = $this->actingAs($manager, 'sanctum')->getJson("/api/v1/tasks/summary?filter[task_category_id]={$repair->id}");

    $response->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.by_status.new', 1);
});

it('offers only active task categories, in sort order, for the task form', function () {
    $employeeUser = userWithRole('employee', Organization::factory()->create());
    $second = TaskCategory::factory()->create(['sort_order' => 2]);
    $first = TaskCategory::factory()->create(['sort_order' => 1]);
    TaskCategory::factory()->inactive()->create(['sort_order' => 0]);

    $response = $this->actingAs($employeeUser, 'sanctum')->getJson('/api/v1/tasks/options');

    $response->assertOk()
        ->assertJsonCount(2, 'data.categories')
        ->assertJsonPath('data.categories.0.id', $first->id)
        ->assertJsonPath('data.categories.1.id', $second->id);
});
