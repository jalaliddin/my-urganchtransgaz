<?php

use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamAttempt;
use App\Models\ExamQuestion;
use App\Models\Organization;
use App\Notifications\ExamFailed;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('returns 401 when listing exams without authentication', function () {
    $this->getJson('/api/v1/exams')->assertStatus(401);
});

it('lets safety-manager create an exam', function () {
    $safety = userWithRole('safety-manager', Organization::factory()->create());

    $response = $this->actingAs($safety, 'sanctum')->postJson('/api/v1/exams', [
        'title' => 'Yong\'in xavfsizligi',
        'duration_minutes' => 30,
        'passing_score' => 70,
        'attempts_allowed' => 2,
        'status' => 'active',
    ]);

    $response->assertCreated()->assertJsonPath('data.status', 'active');
});

it('forbids a plain employee from creating an exam', function () {
    $employee = userWithRole('employee', Organization::factory()->create());

    $this->actingAs($employee, 'sanctum')->postJson('/api/v1/exams', [
        'title' => 'Not allowed',
        'duration_minutes' => 30,
        'passing_score' => 70,
    ])->assertStatus(403);
});

it('forbids a manager from creating an exam', function () {
    $manager = userWithRole('manager', Organization::factory()->create());

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/exams', [
        'title' => 'Not allowed',
        'duration_minutes' => 30,
        'passing_score' => 70,
    ])->assertStatus(403);
});

it('only shows an employee exams that apply to their own organization', function () {
    $organization = Organization::factory()->create();
    $employeeUser = userWithRole('employee', $organization);
    Exam::factory()->create(['organization_id' => $organization->id, 'status' => 'active']);
    Exam::factory()->create(['organization_id' => Organization::factory()->create()->id, 'status' => 'active']);
    Exam::factory()->create(['organization_id' => null, 'status' => 'active']);

    $response = $this->actingAs($employeeUser, 'sanctum')->getJson('/api/v1/exams');

    $response->assertOk()->assertJsonPath('meta.total', 2);
});

it('forbids an employee from attempting an exam outside their organization', function () {
    $employeeUser = userWithRole('employee', Organization::factory()->create());
    $exam = Exam::factory()->create(['organization_id' => Organization::factory()->create()->id, 'status' => 'active']);

    $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/exams/{$exam->id}/attempts")->assertStatus(403);
});

/**
 * Builds an exam with one question of each type directly via factories
 * (not the authoring endpoint, which has its own dedicated coverage
 * above) — one single_choice (Tashkent correct), one true_false (True
 * correct), one multiple_choice (Helmet + Gloves correct).
 */
function createQuizExam(Organization $organization): Exam
{
    $exam = Exam::factory()->create([
        'organization_id' => $organization->id,
        'status' => 'active',
        'passing_score' => 60,
        'attempts_allowed' => 2,
    ]);

    $q1 = ExamQuestion::factory()->create(['exam_id' => $exam->id, 'type' => 'single_choice', 'order' => 1]);
    ExamAnswer::factory()->create(['question_id' => $q1->id, 'answer' => 'Samarkand', 'is_correct' => false]);
    ExamAnswer::factory()->correct()->create(['question_id' => $q1->id, 'answer' => 'Tashkent']);

    $q2 = ExamQuestion::factory()->create(['exam_id' => $exam->id, 'type' => 'true_false', 'order' => 2]);
    ExamAnswer::factory()->correct()->create(['question_id' => $q2->id, 'answer' => 'True']);
    ExamAnswer::factory()->create(['question_id' => $q2->id, 'answer' => 'False', 'is_correct' => false]);

    $q3 = ExamQuestion::factory()->create(['exam_id' => $exam->id, 'type' => 'multiple_choice', 'order' => 3]);
    ExamAnswer::factory()->correct()->create(['question_id' => $q3->id, 'answer' => 'Helmet']);
    ExamAnswer::factory()->create(['question_id' => $q3->id, 'answer' => 'Sandals', 'is_correct' => false]);
    ExamAnswer::factory()->correct()->create(['question_id' => $q3->id, 'answer' => 'Gloves']);

    return $exam->fresh(['questions.answers' => fn ($query) => $query->orderBy('id')]);
}

it('validates that a single_choice question has exactly one correct answer', function () {
    $organization = Organization::factory()->create();
    $exam = Exam::factory()->create(['organization_id' => $organization->id]);
    $safety = userWithRole('safety-manager', $organization);

    $this->actingAs($safety, 'sanctum')->postJson("/api/v1/exams/{$exam->id}/questions", [
        'question' => 'Bad question',
        'type' => 'single_choice',
        'answers' => [
            ['answer' => 'A', 'is_correct' => true],
            ['answer' => 'B', 'is_correct' => true],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('answers');
});

it('grades a fully correct attempt as 100 percent and passed', function () {
    $organization = Organization::factory()->create();
    $exam = createQuizExam($organization);
    $employeeUser = userWithRole('employee', $organization);

    $start = $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/exams/{$exam->id}/attempts");
    $start->assertCreated();
    $attemptId = $start->json('data.attempt.id');

    [$q1, $q2, $q3] = $exam->questions;
    $answers = [
        ['question_id' => $q1->id, 'answer_ids' => [$q1->answers->firstWhere('is_correct', true)->id]],
        ['question_id' => $q2->id, 'answer_ids' => [$q2->answers->firstWhere('is_correct', true)->id]],
        ['question_id' => $q3->id, 'answer_ids' => $q3->answers->where('is_correct', true)->pluck('id')->all()],
    ];

    $submit = $this->actingAs($employeeUser, 'sanctum')
        ->postJson("/api/v1/exams/{$exam->id}/attempts/{$attemptId}/submit", ['answers' => $answers]);

    $submit->assertOk()
        ->assertJsonPath('data.percentage', 100)
        ->assertJsonPath('data.passed', true)
        ->assertJsonPath('data.status', 'completed');
});

it('audit-logs exam completion', function () {
    $organization = Organization::factory()->create();
    $exam = createQuizExam($organization);
    $employeeUser = userWithRole('employee', $organization);

    $start = $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/exams/{$exam->id}/attempts");
    $attemptId = $start->json('data.attempt.id');

    [$q1, $q2, $q3] = $exam->questions;
    $answers = [
        ['question_id' => $q1->id, 'answer_ids' => [$q1->answers->firstWhere('is_correct', true)->id]],
        ['question_id' => $q2->id, 'answer_ids' => [$q2->answers->firstWhere('is_correct', true)->id]],
        ['question_id' => $q3->id, 'answer_ids' => $q3->answers->where('is_correct', true)->pluck('id')->all()],
    ];

    $this->actingAs($employeeUser, 'sanctum')
        ->postJson("/api/v1/exams/{$exam->id}/attempts/{$attemptId}/submit", ['answers' => $answers]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'completed',
        'module' => 'exams',
        'entity_id' => $exam->id,
    ]);
});

it('does not award credit for a partially-selected multiple_choice answer', function () {
    $organization = Organization::factory()->create();
    $exam = createQuizExam($organization);
    $employeeUser = userWithRole('employee', $organization);

    $start = $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/exams/{$exam->id}/attempts");
    $attemptId = $start->json('data.attempt.id');

    [$q1, $q2, $q3] = $exam->questions;
    $correctMultiple = $q3->answers->where('is_correct', true)->pluck('id');

    $answers = [
        ['question_id' => $q1->id, 'answer_ids' => [$q1->answers->firstWhere('is_correct', true)->id]],
        ['question_id' => $q2->id, 'answer_ids' => [$q2->answers->firstWhere('is_correct', true)->id]],
        // only one of the two correct multiple_choice answers selected
        ['question_id' => $q3->id, 'answer_ids' => [$correctMultiple->first()]],
    ];

    $submit = $this->actingAs($employeeUser, 'sanctum')
        ->postJson("/api/v1/exams/{$exam->id}/attempts/{$attemptId}/submit", ['answers' => $answers]);

    // 2 of 3 questions correct = 66.67%, still passes a 60% bar
    $submit->assertOk()->assertJsonPath('data.percentage', 66.67);
});

it('notifies the employee when they fail an exam, mentioning remaining attempts', function () {
    $organization = Organization::factory()->create();
    $exam = createQuizExam($organization);
    $employeeUser = userWithRole('employee', $organization);

    $start = $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/exams/{$exam->id}/attempts");
    $attemptId = $start->json('data.attempt.id');

    [$q1, $q2, $q3] = $exam->questions;
    $wrongAnswer = $q1->answers->firstWhere('is_correct', false);

    $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/exams/{$exam->id}/attempts/{$attemptId}/submit", [
        'answers' => [
            ['question_id' => $q1->id, 'answer_ids' => [$wrongAnswer->id]],
            ['question_id' => $q2->id, 'answer_ids' => []],
            ['question_id' => $q3->id, 'answer_ids' => []],
        ],
    ])->assertOk()->assertJsonPath('data.passed', false);

    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $employeeUser->id,
        'type' => ExamFailed::class,
    ]);
});

it('blocks starting a new attempt once attempts_allowed is exhausted', function () {
    $organization = Organization::factory()->create();
    $exam = Exam::factory()->create(['organization_id' => $organization->id, 'status' => 'active', 'attempts_allowed' => 1]);
    $employeeUser = userWithRole('employee', $organization);

    ExamAttempt::factory()->completed(40, false)->create([
        'exam_id' => $exam->id,
        'employee_id' => $employeeUser->employee->id,
    ]);

    $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/exams/{$exam->id}/attempts")->assertStatus(409);
});

it('blocks starting a new attempt once the employee has already passed', function () {
    $organization = Organization::factory()->create();
    $exam = Exam::factory()->create(['organization_id' => $organization->id, 'status' => 'active', 'attempts_allowed' => 5]);
    $employeeUser = userWithRole('employee', $organization);

    ExamAttempt::factory()->completed(90, true)->create([
        'exam_id' => $exam->id,
        'employee_id' => $employeeUser->employee->id,
    ]);

    $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/exams/{$exam->id}/attempts")->assertStatus(409);
});

it('flags a late submission without penalizing the score', function () {
    $organization = Organization::factory()->create();
    $exam = createQuizExam($organization);
    $exam->update(['duration_minutes' => 10]);
    $employeeUser = userWithRole('employee', $organization);

    $start = $this->actingAs($employeeUser, 'sanctum')->postJson("/api/v1/exams/{$exam->id}/attempts");
    $attemptId = $start->json('data.attempt.id');

    ExamAttempt::whereKey($attemptId)->update(['started_at' => now()->subMinutes(20)]);

    [$q1, $q2, $q3] = $exam->questions;
    $answers = [
        ['question_id' => $q1->id, 'answer_ids' => [$q1->answers->firstWhere('is_correct', true)->id]],
        ['question_id' => $q2->id, 'answer_ids' => [$q2->answers->firstWhere('is_correct', true)->id]],
        ['question_id' => $q3->id, 'answer_ids' => $q3->answers->where('is_correct', true)->pluck('id')->all()],
    ];

    $submit = $this->actingAs($employeeUser, 'sanctum')
        ->postJson("/api/v1/exams/{$exam->id}/attempts/{$attemptId}/submit", ['answers' => $answers]);

    $submit->assertOk()->assertJsonPath('data.is_late', true)->assertJsonPath('data.percentage', 100);
});

it('lets safety-manager view the results roster and statistics', function () {
    $organization = Organization::factory()->create();
    $exam = Exam::factory()->create(['organization_id' => $organization->id, 'status' => 'active']);
    // No organization/Employee record for the safety manager here — they'd
    // otherwise count as one more "eligible" employee in this exam's own
    // organization and throw off the roster counts below.
    $safety = userWithRole('safety-manager');

    $passedUser = userWithRole('employee', $organization);
    $failedUser = userWithRole('employee', $organization);
    userWithRole('employee', $organization); // not taken

    ExamAttempt::factory()->completed(90, true)->create(['exam_id' => $exam->id, 'employee_id' => $passedUser->employee->id]);
    ExamAttempt::factory()->completed(40, false)->create(['exam_id' => $exam->id, 'employee_id' => $failedUser->employee->id]);

    $response = $this->actingAs($safety, 'sanctum')->getJson("/api/v1/exams/{$exam->id}/results");

    $response->assertOk()
        ->assertJsonPath('meta.stats.total_eligible', 3)
        ->assertJsonPath('meta.stats.passed', 1)
        ->assertJsonPath('meta.stats.failed', 1)
        ->assertJsonPath('meta.stats.not_taken', 1);
});

it('forbids a manager from viewing exam results', function () {
    $organization = Organization::factory()->create();
    $exam = Exam::factory()->create(['organization_id' => $organization->id]);
    $manager = userWithRole('manager', $organization);

    $this->actingAs($manager, 'sanctum')->getJson("/api/v1/exams/{$exam->id}/results")->assertStatus(403);
});
