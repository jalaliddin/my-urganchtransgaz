<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ExamQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The view an employee sees while taking the exam — deliberately omits
 * `is_correct` on every answer option. See ExamQuestionResource for the
 * admin-facing equivalent that does include it.
 *
 * @mixin ExamQuestion
 */
class ExamAttemptQuestionResource extends JsonResource
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
            'question' => $this->question,
            'type' => $this->type,
            'points' => $this->points,
            'answers' => $this->answers->map(fn ($answer) => [
                'id' => $answer->id,
                'answer' => $answer->answer,
            ]),
        ];
    }
}
