<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteTaskRequest extends FormRequest
{
    /**
     * Authorization is a pure route-model check, done via Gate::authorize()
     * in the controller — see UpdateTaskProgressRequest.
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
            'result' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
