<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignIssueExecutorsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('assignExecutors', $this->route('issue'));
    }

    /**
     * The full new set of executors — it replaces the current one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'executor_ids' => ['required', 'array', 'min:1', 'max:30'],
            'executor_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * Every executor must be a current employee of the issue's organization,
     * the same rule as when an issue is reported (`StoreIssueRequest`).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $ids = array_unique($this->input('executor_ids'));

                $eligible = Employee::issueExecutors()
                    ->where('organization_id', $this->route('issue')->organization_id)
                    ->whereIn('id', $ids)
                    ->count();

                if ($eligible !== count($ids)) {
                    $validator->errors()->add('executor_ids', 'Ijrochilar muammo tegishli tashkilotning amaldagi xodimlari bo\'lishi kerak.');
                }
            },
        ];
    }
}
