<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIssueCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('issue_categories.manage');
    }

    /**
     * The `code` is generated from the name (see the controller) rather than
     * typed by the person adding a category — it's an internal stable key.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('issue_categories', 'name')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'status' => ['nullable', Rule::enum(ActiveStatus::class)],
        ];
    }
}
