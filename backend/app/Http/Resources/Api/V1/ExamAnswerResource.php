<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ExamAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-facing — includes is_correct. Never served to an employee taking
 * the exam; see ExamAttemptQuestionResource for that view.
 *
 * @mixin ExamAnswer
 */
class ExamAnswerResource extends JsonResource
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
            'answer' => $this->answer,
            'is_correct' => $this->is_correct,
        ];
    }
}
