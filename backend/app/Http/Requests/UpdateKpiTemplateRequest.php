<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKpiTemplateRequest extends FormRequest
{
    /**
     * Authorization (creator/manager-in-scope over the existing
     * template) is a pure route-model check, done via Gate::authorize()
     * in the controller.
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(ActiveStatus::class)],
        ];
    }
}
