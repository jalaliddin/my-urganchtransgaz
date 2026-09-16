<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeKpiRequest extends FormRequest
{
    /**
     * Authorization (`kpi.manage` + scope over the existing record,
     * only while it's still a draft) is a pure route-model check, done
     * via Gate::authorize() in the controller.
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
            'target_value' => ['sometimes', 'required', 'numeric', 'min:0'],
            'actual_value' => ['nullable', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
