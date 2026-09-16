<?php

namespace App\Http\Requests;

use App\Enums\ActiveStatus;
use App\Enums\KpiCalculationType;
use App\Enums\KpiPeriodType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKpiIndicatorRequest extends FormRequest
{
    /**
     * Authorization (`kpi.manage` + scope over the parent template) is a
     * pure route-model check, done via Gate::authorize() in the
     * controller.
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
            'description' => ['nullable', 'string'],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
            'target' => ['required', 'numeric', 'min:0.01'],
            'measurement_unit' => ['nullable', 'string', 'max:50'],
            'calculation_type' => ['required', Rule::enum(KpiCalculationType::class)],
            'period' => ['required', Rule::enum(KpiPeriodType::class)],
            'status' => ['nullable', Rule::enum(ActiveStatus::class)],
        ];
    }
}
