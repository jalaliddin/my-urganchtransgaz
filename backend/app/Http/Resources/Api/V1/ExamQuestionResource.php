<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ExamQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-facing (question authoring) — includes correct answers. Never
 * served to an employee taking the exam; see ExamAttemptQuestionResource.
 *
 * @mixin ExamQuestion
 */
class ExamQuestionResource extends JsonResource
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
            'question' => $this->question,
            'type' => $this->type,
            'points' => $this->points,
            'order' => $this->order,
            'answers' => ExamAnswerResource::collection($this->whenLoaded('answers')),
        ];
    }
}
