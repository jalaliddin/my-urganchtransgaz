<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Exam
 */
class ExamResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'organization_id' => $this->organization_id,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'created_by' => $this->created_by,

            'duration_minutes' => $this->duration_minutes,
            'passing_score' => $this->passing_score,
            'attempts_allowed' => $this->attempts_allowed,

            // Explicit format, not the bare cast value — see
            // EmployeeResource for why a "date" cast still needs this.
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,

            'questions_count' => $this->whenCounted('questions'),
            'questions' => ExamQuestionResource::collection($this->whenLoaded('questions')),

            // Only present when the controller eager-loaded `attempts`
            // constrained to the viewing employee — see ExamController::index().
            'my_attempt_summary' => $this->whenLoaded('attempts', function () {
                if ($this->attempts->isEmpty()) {
                    return null;
                }

                $best = $this->attempts->sortByDesc('percentage')->first();

                return [
                    'attempts_used' => $this->attempts->count(),
                    'best_percentage' => $best->percentage,
                    'passed' => $this->attempts->contains('passed', true),
                    'last_status' => $this->attempts->sortByDesc('created_at')->first()->status,
                ];
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
