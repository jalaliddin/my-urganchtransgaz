<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('task_categories.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('task_categories', 'name')->ignore($this->route('task_category')),
            ],
            'color' => ['sometimes', 'required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'status' => ['sometimes', Rule::enum(ActiveStatus::class)],
        ];
    }
}
