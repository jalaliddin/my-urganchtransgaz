<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Kpi\CalculateKpiScore;
use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateEmployeeKpiRequest;
use App\Http\Requests\StoreEmployeeKpiRequest;
use App\Http\Requests\UpdateEmployeeKpiRequest;
use App\Http\Resources\Api\V1\EmployeeKpiResource;
use App\Models\Employee;
use App\Models\EmployeeKpi;
use App\Models\KpiIndicator;
use App\Models\KpiPeriod;
use App\Models\KpiTemplate;
use App\Models\User;
use App\Notifications\KpiPublished;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class KpiController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private CalculateKpiScore $calculateScore,
    ) {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', EmployeeKpi::class);

        $user = request()->user();

        $results = QueryBuilder::for(EmployeeKpi::class)
            ->with(['employee', 'period', 'indicator'])
            ->allowedFilters(
                AllowedFilter::exact('employee_id'),
                AllowedFilter::exact('kpi_period_id'),
                AllowedFilter::exact('kpi_indicator_id'),
            )
            ->defaultSort('-created_at')
            ->when(! $user->can('kpi.manage'), fn ($query) => $query->whereNotNull('approved_at'))
            ->when(! $this->isSupervisor($user), fn ($query) => $query->where('employee_id', $user->employee?->id))
            ->when($this->isSupervisor($user) && ! $user->hasCentralAccess(), function ($query) use ($user) {
                $query->whereHas('employee', function ($employeeQuery) use ($user) {
                    $employeeQuery->where('organization_id', $user->employee?->organization_id);

                    if ($user->hasRole('department-manager')) {
                        $employeeQuery->where('department_id', $user->employee?->department_id);
                    }
                });
            })
            ->paginate(request()->integer('per_page', 15));

        return $this->success(EmployeeKpiResource::collection($results), meta: $this->paginationMeta($results));
    }

    /**
     * The authenticated employee's own results across every period —
     * §22's literal `/api/v1/kpi/my`.
     */
    public function my(): JsonResponse
    {
        $employee = request()->user()->employee()->firstOrFail();

        $results = EmployeeKpi::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('approved_at')
            ->with(['period', 'indicator'])
            ->orderByDesc('created_at')
            ->paginate(request()->integer('per_page', 20));

        return $this->success(EmployeeKpiResource::collection($results), meta: $this->paginationMeta($results));
    }

    /**
     * Enter a target/actual value for one employee on one indicator/
     * period — draft until approved.
     */
    public function store(StoreEmployeeKpiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $indicator = KpiIndicator::findOrFail($data['kpi_indicator_id']);

        $employeeKpi = EmployeeKpi::create([
            'employee_id' => $data['employee_id'],
            'kpi_period_id' => $data['kpi_period_id'],
            'kpi_indicator_id' => $data['kpi_indicator_id'],
            'target_value' => $data['target_value'] ?? $indicator->target,
            'actual_value' => $data['actual_value'] ?? null,
            'weight' => $indicator->weight,
            'comment' => $data['comment'] ?? null,
        ]);

        $this->auditLog->log('created', 'employee_kpis', $employeeKpi);

        return $this->success(new EmployeeKpiResource($employeeKpi->load(['employee', 'period', 'indicator'])), 'KPI yozuvi qo\'shildi.', 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeKpiRequest $request, EmployeeKpi $employeeKpi): JsonResponse
    {
        Gate::authorize('manage', $employeeKpi);

        if ($employeeKpi->approved_at !== null) {
            return $this->error('Bu KPI natijasi allaqachon tasdiqlangan.', 409);
        }

        $employeeKpi->update($request->validated());

        $this->auditLog->log('updated', 'employee_kpis', $employeeKpi, newValues: $request->validated());

        return $this->success(new EmployeeKpiResource($employeeKpi->load(['employee', 'period', 'indicator'])), 'KPI yozuvi yangilandi.');
    }

    /**
     * Compute the score, stamp approval, and publish the result to the
     * employee.
     */
    public function approve(EmployeeKpi $employeeKpi): JsonResponse
    {
        Gate::authorize('approve', $employeeKpi);

        if ($employeeKpi->approved_at !== null) {
            return $this->error('Bu KPI natijasi allaqachon tasdiqlangan.', 409);
        }

        if ($employeeKpi->actual_value === null) {
            return $this->error('Avval haqiqiy qiymatni kiriting.', 409);
        }

        $employeeKpi->load('indicator');
        $result = $this->calculateScore->handle($employeeKpi);

        $employeeKpi->update([
            'score' => $result['score'],
            'weighted_score' => $result['weighted_score'],
            'approved_by' => request()->user()->id,
            'approved_at' => now(),
        ]);

        $this->auditLog->log('approved', 'employee_kpis', $employeeKpi);

        $employeeKpi->load('employee.user');
        $employeeKpi->employee->user?->notify(new KpiPublished($employeeKpi));

        return $this->success(new EmployeeKpiResource($employeeKpi->load(['employee', 'period', 'indicator'])), 'KPI natijasi e\'lon qilindi.');
    }

    /**
     * Bulk-create draft rows for every eligible employee × the
     * template's indicators whose cadence matches the period's type —
     * idempotent, skips any (employee, period, indicator) that already
     * exists.
     */
    public function generate(GenerateEmployeeKpiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $period = KpiPeriod::findOrFail($data['kpi_period_id']);
        $template = KpiTemplate::with('indicators')->findOrFail($data['kpi_template_id']);

        $organizationId = $data['organization_id'] ?? (! $user->hasCentralAccess() ? $user->employee?->organization_id : null);
        $departmentId = $data['department_id'] ?? null;

        $indicators = $template->indicators->where('period', $period->period_type->value)->where('status', 'active');

        $employees = Employee::query()
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->get();

        $created = 0;

        foreach ($employees as $employee) {
            foreach ($indicators as $indicator) {
                $exists = EmployeeKpi::where('employee_id', $employee->id)
                    ->where('kpi_period_id', $period->id)
                    ->where('kpi_indicator_id', $indicator->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                EmployeeKpi::create([
                    'employee_id' => $employee->id,
                    'kpi_period_id' => $period->id,
                    'kpi_indicator_id' => $indicator->id,
                    'target_value' => $indicator->target,
                    'weight' => $indicator->weight,
                ]);

                $created++;
            }
        }

        return $this->success(['created' => $created], "{$created} ta KPI yozuvi yaratildi.", 201);
    }

    /**
     * Database-aggregated overall score + department ranking for a
     * period, per Module 11's dashboard (§53: never loop-summing in PHP).
     */
    public function report(): JsonResponse
    {
        Gate::authorize('viewAny', EmployeeKpi::class);

        $user = request()->user();
        $periodId = request()->integer('period_id');

        if (! $periodId) {
            return $this->error('period_id talab qilinadi.', 422);
        }

        $rows = EmployeeKpi::query()
            ->join('employees', 'employees.id', '=', 'employee_kpis.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->where('employee_kpis.kpi_period_id', $periodId)
            ->whereNotNull('employee_kpis.approved_at')
            ->when(! $this->isSupervisor($user), fn ($query) => $query->where('employees.id', $user->employee?->id))
            ->when($this->isSupervisor($user) && ! $user->hasCentralAccess(), function ($query) use ($user) {
                $query->where('employees.organization_id', $user->employee?->organization_id);

                if ($user->hasRole('department-manager')) {
                    $query->where('employees.department_id', $user->employee?->department_id);
                }
            })
            ->selectRaw('departments.id as department_id')
            ->selectRaw('COALESCE(departments.name, ?) as department_name', ['—'])
            ->selectRaw('COUNT(DISTINCT employee_kpis.employee_id) as employees_count')
            ->selectRaw('ROUND(SUM(employee_kpis.weighted_score) / COUNT(DISTINCT employee_kpis.employee_id), 2) as average_score')
            ->groupBy('department_id', 'department_name')
            ->orderByDesc('average_score')
            ->get();

        return $this->success($rows);
    }

    private function isSupervisor(User $user): bool
    {
        return $user->hasAnyRole(['manager', 'department-manager', 'organization-admin']) || $user->hasCentralAccess();
    }
}
