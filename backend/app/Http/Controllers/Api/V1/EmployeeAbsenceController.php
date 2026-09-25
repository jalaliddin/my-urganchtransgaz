<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Absences\CalculateLeaveBalance;
use App\Actions\Absences\CancelEmployeeAbsence;
use App\Actions\Absences\SaveEmployeeAbsence;
use App\Actions\Export\ExportRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeAbsenceRequest;
use App\Http\Requests\UpdateEmployeeAbsenceRequest;
use App\Http\Resources\Api\V1\EmployeeAbsenceResource;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Policies\EmployeeAbsencePolicy;
use App\Services\AuditLogService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeAbsenceController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(ExportRecords $export): JsonResponse|Response
    {
        Gate::authorize('viewAny', EmployeeAbsence::class);

        $user = request()->user();

        $query = QueryBuilder::for(EmployeeAbsence::class)
            ->with(['employee.department', 'employee.organization', 'creator'])
            ->allowedFilters(
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::callback('state', fn (Builder $query, string $state) => $this->filterByState($query, $state)),
                AllowedFilter::callback('organization_id', fn (Builder $query, $id) => $query->whereHas('employee', fn ($q) => $q->where('organization_id', $id))),
                AllowedFilter::callback('department_id', fn (Builder $query, $id) => $query->whereHas('employee', fn ($q) => $q->where('department_id', $id))),
                AllowedFilter::callback('search', fn (Builder $query, string $term) => $query->whereHas('employee', fn ($q) => $q->where(function ($q) use ($term) {
                    $q->where('last_name', 'like', "%{$term}%")
                        ->orWhere('first_name', 'like', "%{$term}%")
                        ->orWhere('employee_number', 'like', "%{$term}%");
                }))),
            )
            ->allowedSorts('start_date', 'end_date', 'created_at')
            ->defaultSort('-start_date');

        // Plain if, never ->when() — see EmployeeController::index().
        if ($from = request()->date('from')) {
            $query->where('end_date', '>=', $from->toDateString());
        }

        if ($to = request()->date('to')) {
            $query->where('start_date', '<=', $to->toDateString());
        }

        if (! $user->can('absences.view')) {
            $query->where('employee_id', $user->employee?->id);
        } elseif (! $user->hasCentralAccess()) {
            $query->whereHas('employee', function (Builder $employeeQuery) use ($user) {
                $employeeQuery->where('organization_id', $user->employee?->organization_id);

                if ($user->hasRole('department-manager')) {
                    $employeeQuery->where('department_id', $user->employee?->department_id);
                }
            });
        }

        if ($format = request()->string('export')->toString()) {
            return $export->stream($query->getEloquentBuilder(), [
                'employee.employee_number' => 'Tabel raqami',
                'employee.last_name' => 'Familiyasi',
                'employee.first_name' => 'Ismi',
                'employee.department.name' => 'Bo\'lim',
                'type_label' => 'Turi',
                'start_date' => 'Boshlanishi',
                'end_date' => 'Tugashi',
                'days' => 'Kunlar',
                'document_number' => 'Buyruq / varaqa raqami',
                'destination' => 'Safar manzili',
            ], $format, 'absences');
        }

        $absences = $query->paginate(request()->integer('per_page', 15));

        return $this->success(
            EmployeeAbsenceResource::collection($absences),
            meta: $this->paginationMeta($absences)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeAbsenceRequest $request, SaveEmployeeAbsence $action): JsonResponse
    {
        $employee = Employee::findOrFail($request->integer('employee_id'));

        $absence = $action->create($employee, $request->user(), $request->validated(), $request->file('file'));

        $this->auditLog->log('created', 'absences', $absence, newValues: $this->auditValues($absence));

        return $this->success(new EmployeeAbsenceResource($absence->load('employee')), 'Yozuv saqlandi.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(EmployeeAbsence $absence): JsonResponse
    {
        Gate::authorize('view', $absence);

        $absence->load(['employee.department', 'employee.organization', 'creator']);

        return $this->success(new EmployeeAbsenceResource($absence));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeAbsenceRequest $request, EmployeeAbsence $absence, SaveEmployeeAbsence $action): JsonResponse
    {
        $oldValues = $this->auditValues($absence);

        $absence = $action->update($absence, $request->validated(), $request->file('file'));

        $this->auditLog->log('updated', 'absences', $absence, $oldValues, $this->auditValues($absence));

        return $this->success(new EmployeeAbsenceResource($absence->load('employee')), 'Yozuv yangilandi.');
    }

    /**
     * Cancel instead of delete: the record stays in the employee's history.
     */
    public function cancel(Request $request, EmployeeAbsence $absence, CancelEmployeeAbsence $action): JsonResponse
    {
        Gate::authorize('cancel', $absence);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $action->handle($absence, $request->user(), $data['reason'] ?? null);

        $this->auditLog->log('cancelled', 'absences', $absence, newValues: ['reason' => $data['reason'] ?? null]);

        return $this->success(new EmployeeAbsenceResource($absence->load('employee')), 'Yozuv bekor qilindi.');
    }

    /**
     * Stream the attached order or certificate. Never a public URL.
     */
    public function download(EmployeeAbsence $absence): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $absence);

        if (! $absence->file_path || ! Storage::disk('local')->exists($absence->file_path)) {
            return $this->error('Fayl topilmadi.', 404);
        }

        return Storage::disk('local')->download($absence->file_path, $absence->file_name);
    }

    /**
     * Annual leave used/remaining for one employee in one year.
     */
    public function balance(Request $request, Employee $employee, CalculateLeaveBalance $calculate, EmployeeAbsencePolicy $policy): JsonResponse
    {
        abort_unless($policy->canSeeEmployee($request->user(), $employee), 403);

        return $this->success($calculate->handle($employee, $request->integer('year', now()->year)));
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(EmployeeAbsence $absence): array
    {
        return [
            'employee_id' => $absence->employee_id,
            'type' => $absence->type->value,
            'start_date' => $absence->start_date->toDateString(),
            'end_date' => $absence->end_date->toDateString(),
            'document_number' => $absence->document_number,
        ];
    }

    /**
     * @param  Builder<EmployeeAbsence>  $query
     */
    private function filterByState(Builder $query, string $state): void
    {
        $today = Carbon::today()->toDateString();

        match ($state) {
            'cancelled' => $query->whereNotNull('cancelled_at'),
            'upcoming' => $query->whereNull('cancelled_at')->where('start_date', '>', $today),
            'completed' => $query->whereNull('cancelled_at')->where('end_date', '<', $today),
            'current' => $query->whereNull('cancelled_at')->where('start_date', '<=', $today)->where('end_date', '>=', $today),
            // "active", or anything unrecognized: every record still in force.
            default => $query->whereNull('cancelled_at'),
        };
    }
}
