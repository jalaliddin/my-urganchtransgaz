<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Exams\GradeExamAttempt;
use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitExamAttemptRequest;
use App\Http\Resources\Api\V1\ExamAttemptQuestionResource;
use App\Http\Resources\Api\V1\ExamAttemptResource;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Notifications\ExamFailed;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ExamAttemptController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Start a new attempt (or resume one already in progress).
     */
    public function store(Exam $exam): JsonResponse
    {
        Gate::authorize('attempt', $exam);

        $employee = request()->user()->employee;

        if ($exam->status !== ExamStatus::Active) {
            return $this->error('Bu imtihon faol emas.', 409);
        }

        $today = Carbon::today()->toDateString();

        if (($exam->start_date && $today < $exam->start_date->toDateString())
            || ($exam->end_date && $today > $exam->end_date->toDateString())) {
            return $this->error('Bu imtihon hozircha ochiq emas.', 409);
        }

        $existingAttempts = ExamAttempt::where('exam_id', $exam->id)->where('employee_id', $employee->id);

        if ((clone $existingAttempts)->where('passed', true)->exists()) {
            return $this->error('Siz bu imtihondan allaqachon o\'tgansiz.', 409);
        }

        $inProgress = (clone $existingAttempts)->where('status', AttemptStatus::InProgress)->first();

        if (! $inProgress && (clone $existingAttempts)->count() >= $exam->attempts_allowed) {
            return $this->error('Urinishlar soni tugadi.', 409);
        }

        $attempt = $inProgress ?? ExamAttempt::create([
            'exam_id' => $exam->id,
            'employee_id' => $employee->id,
            'status' => AttemptStatus::InProgress,
            'started_at' => now(),
        ]);

        $questions = $exam->questions()->with('answers')->get();

        return $this->success([
            'attempt' => new ExamAttemptResource($attempt),
            'questions' => ExamAttemptQuestionResource::collection($questions),
        ], 'Imtihon boshlandi.', 201);
    }

    /**
     * Grade and finalize an attempt.
     */
    public function submit(SubmitExamAttemptRequest $request, Exam $exam, ExamAttempt $attempt, GradeExamAttempt $grader): JsonResponse
    {
        Gate::authorize('attempt', $exam);
        abort_unless($attempt->exam_id === $exam->id, 404);
        abort_unless($attempt->employee_id === request()->user()->employee?->id, 403);

        if ($attempt->status !== AttemptStatus::InProgress) {
            return $this->error('Bu urinish allaqachon yakunlangan.', 409);
        }

        $attempt = $grader->handle($attempt, $request->validated('answers'));

        if (! $attempt->passed) {
            $remaining = $exam->attempts_allowed - ExamAttempt::where('exam_id', $exam->id)->where('employee_id', $attempt->employee_id)->count();
            request()->user()->notify(new ExamFailed($exam, $remaining));
        }

        $this->auditLog->log('completed', 'exams', $exam, newValues: ['attempt_id' => $attempt->id, 'passed' => $attempt->passed]);

        return $this->success(new ExamAttemptResource($attempt), 'Imtihon yakunlandi.');
    }

    /**
     * Review a finished attempt.
     */
    public function show(Exam $exam, ExamAttempt $attempt): JsonResponse
    {
        abort_unless($attempt->exam_id === $exam->id, 404);

        $user = request()->user();
        $isAdmin = $user->hasRole('safety-manager') || $user->hasCentralAccess();

        if (! $isAdmin) {
            Gate::authorize('attempt', $exam);
            abort_unless($attempt->employee_id === $user->employee?->id, 403);
        }

        $attempt->load(['attemptAnswers', 'exam.questions.answers']);

        return $this->success(new ExamAttemptResource($attempt));
    }
}
