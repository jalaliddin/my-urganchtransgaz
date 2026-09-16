<?php

namespace App\Actions\Exams;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use Illuminate\Support\Carbon;

class GradeExamAttempt
{
    /**
     * Grades every question exact-match (a question is only correct when
     * the selected answer set equals its correct-answer set exactly —
     * this covers single_choice/true_false naturally, since both just
     * have a one-answer correct set, with no partial credit for
     * multiple_choice). A late submission is still graded, just flagged.
     *
     * @param  array<int, array{question_id: int, answer_ids: array<int, int>}>  $answers
     */
    public function handle(ExamAttempt $attempt, array $answers): ExamAttempt
    {
        $exam = $attempt->exam()->with('questions.answers')->first();

        $totalPoints = 0;
        $earnedPoints = 0;

        foreach ($exam->questions as $question) {
            $totalPoints += $question->points;

            $submitted = collect($answers)->firstWhere('question_id', $question->id);
            $validAnswerIds = $question->answers->pluck('id');

            $selectedIds = collect($submitted['answer_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->intersect($validAnswerIds)
                ->sort()
                ->values();

            $correctIds = $question->answers->where('is_correct', true)->pluck('id')->sort()->values();

            if ($selectedIds->all() === $correctIds->all()) {
                $earnedPoints += $question->points;
            }

            foreach ($selectedIds as $answerId) {
                $attempt->attemptAnswers()->create([
                    'question_id' => $question->id,
                    'answer_id' => $answerId,
                ]);
            }
        }

        $percentage = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0.0;
        $deadline = Carbon::parse($attempt->started_at)->addMinutes($exam->duration_minutes);

        $attempt->update([
            'score' => $earnedPoints,
            'percentage' => $percentage,
            'passed' => $percentage >= $exam->passing_score,
            'status' => AttemptStatus::Completed,
            'completed_at' => now(),
            'is_late' => now()->greaterThan($deadline),
        ]);

        return $attempt->fresh(['attemptAnswers', 'exam.questions.answers']);
    }
}
