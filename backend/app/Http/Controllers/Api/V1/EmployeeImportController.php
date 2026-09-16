<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Employees\CreateEmployeeAction;
use App\Http\Controllers\Controller;
use App\Imports\EmployeeImport;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeImportController extends Controller
{
    /**
     * @var string[]
     */
    private const COLUMNS = [
        'employee_number', 'first_name', 'last_name', 'middle_name',
        'organization_code', 'department_code', 'position_code',
        'gender', 'birth_date', 'hire_date', 'phone', 'email',
        'employment_type', 'status',
    ];

    /**
     * A ready-made CSV with the exact expected headers and one example
     * row — organization/department/position are referenced by their
     * `code`, resolvable from the Organizations/Departments/Positions
     * pages.
     */
    public function template(): StreamedResponse
    {
        Gate::authorize('create', Employee::class);

        $response = new StreamedResponse(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, self::COLUMNS);
            fputcsv($handle, [
                'EMP00099', 'Aziz', 'Karimov', 'Baxtiyor o\'g\'li',
                'UTG-001', '', '',
                'male', '1990-05-12', '2024-01-15', '+998901234567', 'aziz@example.com',
                'full_time', 'active',
            ]);
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="employees-import-template.csv"');

        return $response;
    }

    /**
     * Parses and validates every row. With `dry_run=1` nothing is
     * written — the full per-row preview (valid/invalid/skipped) comes
     * back so the admin can review before committing. Without it, valid
     * rows are created (each through the normal CreateEmployeeAction, so
     * they're audit-logged exactly like a manually-created employee) and
     * invalid/skipped rows are reported, never imported silently.
     */
    public function store(Request $request, CreateEmployeeAction $createEmployee): JsonResponse
    {
        Gate::authorize('create', Employee::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls'],
            'dry_run' => ['sometimes', 'boolean'],
        ]);

        $import = new EmployeeImport($createEmployee, $request->boolean('dry_run'));
        Excel::import($import, $request->file('file'));

        $failures = $import->failures()->map(fn ($failure) => [
            'row' => $failure->row(),
            'attribute' => $failure->attribute(),
            'errors' => $failure->errors(),
        ])->values();

        return $this->success([
            'dry_run' => $request->boolean('dry_run'),
            'imported_count' => $import->importedIds->count(),
            'imported_ids' => $request->boolean('dry_run') ? [] : $import->importedIds->values(),
            'invalid' => $failures,
            'skipped' => $import->skipped->values(),
        ], $request->boolean('dry_run') ? 'Fayl tekshirildi.' : 'Import yakunlandi.');
    }
}
