<?php

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Organization;
use App\Models\Setting;
use App\Notifications\ExamAvailable;
use App\Notifications\ExamDeadlineApproaching;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('notifies an eligible employee about an exam starting soon', function () {
    $organization = Organization::factory()->create();
    $employee = userWithRole('employee', $organization);
    $exam = Exam::factory()->create([
        'organization_id' => $organization->id,
        'status' => 'active',
        'start_date' => Carbon::today()->addDays(3),
        'end_date' => Carbon::today()->addMonth(),
    ]);

    $this->artisan('exams:send-reminders')->assertExitCode(0);

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $employee->id,
        'type' => ExamAvailable::class,
    ]);

    $notification = $employee->notifications()->where('type', ExamAvailable::class)->first();
    expect($notification->data['threshold'])->toBe(3)
        ->and($notification->data['exam_id'])->toBe($exam->id);
});

it('notifies an eligible employee about an approaching deadline', function () {
    $organization = Organization::factory()->create();
    $employee = userWithRole('employee', $organization);
    Exam::factory()->create([
        'organization_id' => $organization->id,
        'status' => 'active',
        'start_date' => Carbon::today()->subMonth(),
        'end_date' => Carbon::today(),
    ]);

    $this->artisan('exams:send-reminders');

    $notification = $employee->notifications()->where('type', ExamDeadlineApproaching::class)->first();
    expect($notification->data['threshold'])->toBe(0);
});

it('does not send a duplicate reminder for the same exam and threshold', function () {
    $organization = Organization::factory()->create();
    userWithRole('employee', $organization);
    Exam::factory()->create([
        'organization_id' => $organization->id,
        'status' => 'active',
        'start_date' => Carbon::today()->addDay(),
        'end_date' => Carbon::today()->addMonth(),
    ]);

    $this->artisan('exams:send-reminders');
    $this->artisan('exams:send-reminders');

    expect(DatabaseNotification::query()->count())->toBe(1);
});

it('does not remind an employee who has already passed the exam', function () {
    $organization = Organization::factory()->create();
    $employee = userWithRole('employee', $organization);
    $exam = Exam::factory()->create([
        'organization_id' => $organization->id,
        'status' => 'active',
        'start_date' => Carbon::today()->addDays(3),
        'end_date' => Carbon::today()->addMonth(),
    ]);

    ExamAttempt::factory()->completed(90, true)->create([
        'exam_id' => $exam->id,
        'employee_id' => $employee->employee->id,
    ]);

    $this->artisan('exams:send-reminders');

    expect($employee->notifications()->count())->toBe(0);
});

it('uses a Settings-configured reminder threshold instead of the default 3/1', function () {
    Setting::set('exams.reminder_thresholds', [5], 'exams');
    $organization = Organization::factory()->create();
    $employee = userWithRole('employee', $organization);
    $exam = Exam::factory()->create([
        'organization_id' => $organization->id,
        'status' => 'active',
        'start_date' => Carbon::today()->addDays(5),
        'end_date' => Carbon::today()->addMonth(),
    ]);

    $this->artisan('exams:send-reminders');

    $notification = $employee->notifications()->where('type', ExamAvailable::class)->first();
    expect($notification)->not->toBeNull()
        ->and($notification->data['threshold'])->toBe(5)
        ->and($notification->data['exam_id'])->toBe($exam->id);
});

it('does not remind about an exam outside the employee organization', function () {
    $employee = userWithRole('employee', Organization::factory()->create());
    Exam::factory()->create([
        'organization_id' => Organization::factory()->create()->id,
        'status' => 'active',
        'start_date' => Carbon::today()->addDays(3),
        'end_date' => Carbon::today()->addMonth(),
    ]);

    $this->artisan('exams:send-reminders');

    expect($employee->notifications()->count())->toBe(0);
});
