<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskProgressRequest extends FormRequest
{
    /**
     * Authorization is a pure route-model check with no request-body
     * dependency, so — matching RejectRequest's convention — it happens
     * via Gate::authorize() in the controller, not here.
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
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
