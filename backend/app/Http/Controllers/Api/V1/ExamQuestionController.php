<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExamQuestionRequest;
use App\Http\Requests\UpdateExamQuestionRequest;
use App\Http\Resources\Api\V1\ExamQuestionResource;
use App\Models\Exam;
use App\Models\ExamQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ExamQuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Exam $exam): JsonResponse
    {
        Gate::authorize('manage', $exam);

        return $this->success(ExamQuestionResource::collection($exam->questions()->with('answers')->get()));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExamQuestionRequest $request, Exam $exam): JsonResponse
    {
        Gate::authorize('manage', $exam);

        $data = $request->validated();

        $question = $exam->questions()->create([
            'question' => $data['question'],
            'type' => $data['type'],
            'points' => $data['points'] ?? 1,
            'order' => $data['order'] ?? ((int) $exam->questions()->max('order') + 1),
        ]);

        foreach ($data['answers'] as $answer) {
            $question->answers()->create($answer);
        }

        return $this->success(new ExamQuestionResource($question->load('answers')), 'Savol qo\'shildi.', 201);
    }

    /**
     * Update the specified resource in storage — replaces its answers
     * wholesale, matching the "replace, don't patch" convention used
     * elsewhere for compound data (e.g. profile contacts).
     */
    public function update(UpdateExamQuestionRequest $request, Exam $exam, ExamQuestion $question): JsonResponse
    {
        Gate::authorize('manage', $exam);
        abort_unless($question->exam_id === $exam->id, 404);

        $data = $request->validated();

        $question->update([
            'question' => $data['question'],
            'type' => $data['type'],
            'points' => $data['points'] ?? $question->points,
            'order' => $data['order'] ?? $question->order,
        ]);

        $question->answers()->delete();

        foreach ($data['answers'] as $answer) {
            $question->answers()->create($answer);
        }

        return $this->success(new ExamQuestionResource($question->load('answers')), 'Savol yangilandi.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Exam $exam, ExamQuestion $question): JsonResponse
    {
        Gate::authorize('manage', $exam);
        abort_unless($question->exam_id === $exam->id, 404);

        $question->delete();

        return $this->success(message: 'Savol o\'chirildi.');
    }
}
