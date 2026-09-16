<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskAttachmentRequest extends FormRequest
{
    /**
     * Authorization is a pure route-model check (can the user view this
     * task at all?), done via Gate::authorize() in the controller.
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
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
