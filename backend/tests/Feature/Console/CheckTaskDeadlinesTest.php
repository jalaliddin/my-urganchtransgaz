<?php

use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\Task;
use App\Notifications\TaskDeadlineApproaching;
use App\Notifications\TaskOverdue;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('notifies each assignee at every deadline threshold', function () {
    $assignee = userWithRole('employee', Organization::factory()->create());
    $task = Task::factory()->create(['status' => TaskStatus::InProgress->value, 'due_date' => Carbon::today()->addDays(3)]);
    $task->assignees()->attach($assignee->employee->id);

    $this->artisan('tasks:check-deadlines')->assertExitCode(0);

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $assignee->id,
        'type' => TaskDeadlineApproaching::class,
    ]);

    $notification = $assignee->notifications()->first();
    expect($notification->data['threshold'])->toBe(3)
        ->and($notification->data['task_id'])->toBe($task->id);
});

it('notifies once when a task is due today', function () {
    $assignee = userWithRole('employee', Organization::factory()->create());
    $task = Task::factory()->create(['status' => TaskStatus::New->value, 'due_date' => Carbon::today()]);
    $task->assignees()->attach($assignee->employee->id);

    $this->artisan('tasks:check-deadlines');

    $notification = $assignee->notifications()->first();
    expect($notification->data['threshold'])->toBe(0);
});

it('does not send a duplicate reminder for the same task and threshold', function () {
    $assignee = userWithRole('employee', Organization::factory()->create());
    $task = Task::factory()->create(['status' => TaskStatus::InProgress->value, 'due_date' => Carbon::today()->addDay()]);
    $task->assignees()->attach($assignee->employee->id);

    $this->artisan('tasks:check-deadlines');
    $this->artisan('tasks:check-deadlines');

    expect($assignee->notifications()->count())->toBe(1);
});

it('does not remind assignees of a task that is not near any threshold', function () {
    $assignee = userWithRole('employee', Organization::factory()->create());
    $task = Task::factory()->create(['status' => TaskStatus::InProgress->value, 'due_date' => Carbon::today()->addDays(10)]);
    $task->assignees()->attach($assignee->employee->id);

    $this->artisan('tasks:check-deadlines');

    expect($assignee->notifications()->count())->toBe(0);
});

it('does not remind assignees of a task already waiting for approval', function () {
    $assignee = userWithRole('employee', Organization::factory()->create());
    $task = Task::factory()->create(['status' => TaskStatus::Waiting->value, 'due_date' => Carbon::today()]);
    $task->assignees()->attach($assignee->employee->id);

    $this->artisan('tasks:check-deadlines');

    expect($assignee->notifications()->count())->toBe(0);
});

it('marks a past-due open task overdue and notifies its assignee', function () {
    $assignee = userWithRole('employee', Organization::factory()->create());
    $task = Task::factory()->create(['status' => TaskStatus::InProgress->value, 'due_date' => Carbon::today()->subDays(2)]);
    $task->assignees()->attach($assignee->employee->id);

    $this->artisan('tasks:check-deadlines');

    expect($task->fresh()->status)->toBe(TaskStatus::Overdue);
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $assignee->id,
        'type' => TaskOverdue::class,
    ]);
});

it('does not mark a waiting task overdue even if its deadline has passed', function () {
    $task = Task::factory()->create(['status' => TaskStatus::Waiting->value, 'due_date' => Carbon::today()->subDays(2)]);

    $this->artisan('tasks:check-deadlines');

    expect($task->fresh()->status)->toBe(TaskStatus::Waiting);
});
