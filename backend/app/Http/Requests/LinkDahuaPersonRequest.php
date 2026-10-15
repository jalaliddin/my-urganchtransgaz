<?php

namespace App\Http\Requests;

use App\Models\AttendanceDevice;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LinkDahuaPersonRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', AttendanceDevice::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'max:64'],
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')],
        ];
    }

    /**
     * Never moves an ID silently: one already on another employee, or an
     * employee who already has another ID, has to be unlinked first.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $userId = $this->string('user_id')->toString();
                $employee = Employee::findOrFail($this->integer('employee_id'));
                $holder = Employee::where('dahua_person_id', $userId)->whereKeyNot($employee->id)->first();

                if ($holder) {
                    $validator->errors()->add('user_id', "Bu Dahua ID allaqachon {$holder->fullName()} xodimiga biriktirilgan.");
                } elseif ($employee->dahua_person_id && $employee->dahua_person_id !== $userId) {
                    $validator->errors()->add('employee_id', "Bu xodimda boshqa Dahua ID bor ({$employee->dahua_person_id}). Avval uni bekor qiling.");
                }
            },
        ];
    }
}
