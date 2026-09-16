<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitExamAttemptRequest extends FormRequest
{
    /**
     * Authorization (must be the attempt's own employee, and the attempt
     * must still be in progress) is checked in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answers' => ['present', 'array'],
            'answers.*.question_id' => ['required', 'integer', Rule::exists('exam_questions', 'id')],
            'answers.*.answer_ids' => ['present', 'array'],
            'answers.*.answer_ids.*' => ['integer', Rule::exists('exam_answers', 'id')],
        ];
    }
}
