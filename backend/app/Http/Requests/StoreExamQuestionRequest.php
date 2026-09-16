<?php

namespace App\Http\Requests;

use App\Enums\QuestionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamQuestionRequest extends FormRequest
{
    /**
     * Authorization is a pure route-model permission/role check, done via
     * Gate::authorize() in the controller.
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
            'question' => ['required', 'string'],
            'type' => ['required', Rule::enum(QuestionType::class)],
            'points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'order' => ['nullable', 'integer', 'min:0'],
            'answers' => ['required', 'array', 'min:2'],
            'answers.*.answer' => ['required', 'string', 'max:500'],
            'answers.*.is_correct' => ['required', 'boolean'],
        ];
    }

    /**
     * A question's correct-answer count must match its type: exactly one
     * for single_choice/true_false, at least one for multiple_choice.
     * true_false is additionally pinned to exactly two options.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('type');
            $answers = collect($this->input('answers', []));
            $correctCount = $answers->where('is_correct', true)->count();

            if ($type === QuestionType::TrueFalse->value && $answers->count() !== 2) {
                $validator->errors()->add('answers', 'A true/false question must have exactly two options.');
            }

            if (in_array($type, [QuestionType::SingleChoice->value, QuestionType::TrueFalse->value], true) && $correctCount !== 1) {
                $validator->errors()->add('answers', 'This question type must have exactly one correct answer.');
            }

            if ($type === QuestionType::MultipleChoice->value && $correctCount < 1) {
                $validator->errors()->add('answers', 'A multiple-choice question must have at least one correct answer.');
            }
        });
    }
}
