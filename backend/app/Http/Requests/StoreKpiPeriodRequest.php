<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use App\Enums\KpiPeriodType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKpiPeriodRequest extends FormRequest
{
    /**
     * Periods are company-wide (no organization/department scope of
     * their own), so this is a pure `kpi.manage` permission check —
     * done via Gate::authorize() in the controller.
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
            'name' => ['required', 'string', 'max:255'],
            'period_type' => ['required', Rule::enum(KpiPeriodType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['nullable', Rule::enum(ActiveStatus::class)],
        ];
    }
}
