<?php

namespace App\Imports;

use App\Actions\Employees\CreateEmployeeAction;
use App\Enums\EmployeeStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Models\Department;
use App\Models\Organization;
use App\Models\Position;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Organization/department/position are resolved by their human-readable
 * `code`, not a raw id — usable in a real spreadsheet. Deliberately
 * excludes passport_number/pinfl/photo (sensitive/binary — not suited to
 * bulk import) and create_account/username/password/role (credential
 * provisioning stays a one-by-one, deliberate action, never a bulk one).
 *
 * @property-read Collection<int, array<string, string>> $skipped rows
 *     that passed field-level validation but failed a cross-field check
 *     (department/position not belonging to the given organization)
 * @property-read Collection<int, int> $importedIds
 */
class EmployeeImport implements SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use Importable;
    use SkipsFailures;

    public Collection $skipped;

    public Collection $importedIds;

    public function __construct(private CreateEmployeeAction $createEmployee, private bool $dryRun)
    {
        $this->skipped = collect();
        $this->importedIds = collect();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            '*.employee_number' => ['required', 'string', 'max:50', 'distinct', Rule::unique('employees', 'employee_number')],
            '*.first_name' => ['required', 'string', 'max:100'],
            '*.last_name' => ['required', 'string', 'max:100'],
            '*.middle_name' => ['nullable', 'string', 'max:100'],
            '*.organization_code' => ['required', 'string', Rule::exists('organizations', 'code')],
            '*.department_code' => ['nullable', 'string', Rule::exists('departments', 'code')],
            '*.position_code' => ['nullable', 'string', Rule::exists('positions', 'code')],
            '*.gender' => ['nullable', Rule::enum(Gender::class)],
            '*.birth_date' => ['nullable', 'date'],
            '*.hire_date' => ['nullable', 'date'],
            '*.phone' => ['nullable', 'string', 'max:30'],
            '*.email' => ['nullable', 'email'],
            '*.employment_type' => ['nullable', Rule::enum(EmploymentType::class)],
            '*.status' => ['nullable', Rule::enum(EmployeeStatus::class)],
        ];
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $this->processRow($index, $row->filter()->all());
        }
    }

    /**
     * @param  array<string, string>  $row
     */
    private function processRow(int $index, array $row): void
    {
        $organization = Organization::where('code', $row['organization_code'])->first();

        $department = isset($row['department_code'])
            ? Department::where('code', $row['department_code'])->first()
            : null;

        if ($department && $department->organization_id !== $organization->id) {
            $this->skipped->push(['row' => $index + 2, 'error' => 'department_code Ushbu tashkilotga tegishli emas.']);

            return;
        }

        $position = isset($row['position_code'])
            ? Position::where('code', $row['position_code'])->first()
            : null;

        if ($position && $position->organization_id !== $organization->id) {
            $this->skipped->push(['row' => $index + 2, 'error' => 'position_code Ushbu tashkilotga tegishli emas.']);

            return;
        }

        if ($this->dryRun) {
            $this->importedIds->push($index + 2);

            return;
        }

        $employee = $this->createEmployee->handle(array_filter([
            'organization_id' => $organization->id,
            'department_id' => $department?->id,
            'position_id' => $position?->id,
            'employee_number' => $row['employee_number'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'middle_name' => $row['middle_name'] ?? null,
            'gender' => $row['gender'] ?? null,
            'birth_date' => $row['birth_date'] ?? null,
            'hire_date' => $row['hire_date'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            // employment_type/status have a NOT NULL column default —
            // omitted entirely rather than sent as null when the sheet
            // leaves them blank, so the schema default actually applies.
            'employment_type' => $row['employment_type'] ?? null,
            'status' => $row['status'] ?? null,
        ], fn ($value) => $value !== null));

        $this->importedIds->push($employee->id);
    }
}
