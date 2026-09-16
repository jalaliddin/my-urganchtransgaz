<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Setting;
use App\Policies\Concerns\ChecksOrganizationScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeDocumentRequest extends FormRequest
{
    use ChecksOrganizationScope;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $ownEmployeeId = $user->employee?->id;
        $targetEmployeeId = $this->integer('employee_id') ?: $ownEmployeeId;

        if ($targetEmployeeId === $ownEmployeeId) {
            return true;
        }

        if (! $user->can('documents.upload')) {
            return false;
        }

        $employee = Employee::find($targetEmployeeId);

        return $employee && $this->withinScope($user, $employee->organization_id, $employee->department_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')],
            'document_type_id' => ['required', 'integer', Rule::exists('document_types', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'expiry_date' => ['nullable', 'date', 'after:issue_date'],
            'file' => [
                'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'extensions:pdf,jpg,jpeg,png',
                'max:'.Setting::get('documents.max_upload_kb', 10240),
            ],
        ];
    }
}
