<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\AttemptStatus;
use App\Models\ExamAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExamAttempt
 */
class ExamAttemptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exam_id' => $this->exam_id,
            'employee_id' => $this->employee_id,

            'score' => $this->score,
            'percentage' => $this->percentage === null ? null : (float) $this->percentage,
            'passed' => $this->passed,
            'status' => $this->status,
            'is_late' => $this->is_late,

            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,

            // Correctness is only ever revealed once the attempt is
            // finished — never while it's still in progress.
            'questions' => $this->when(
                $this->status === AttemptStatus::Completed,
                fn () => $this->exam->questions->map(function ($question) {
                    $selectedIds = $this->attemptAnswers
                        ->where('question_id', $question->id)
                        ->pluck('answer_id');

                    return [
                        'id' => $question->id,
                        'question' => $question->question,
                        'answers' => $question->answers->map(fn ($answer) => [
                            'id' => $answer->id,
                            'answer' => $answer->answer,
                            'is_correct' => $answer->is_correct,
                            'was_selected' => $selectedIds->contains($answer->id),
                        ]),
                    ];
                }),
            ),
        ];
    }
}
