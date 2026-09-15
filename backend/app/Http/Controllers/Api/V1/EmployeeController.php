<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Employees\CreateEmployeeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Models\Employee;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EmployeeController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Employee::class);

        $user = request()->user();

        $employees = QueryBuilder::for(Employee::class)
            ->with(['organization', 'department', 'position'])
            ->allowedFilters(
                'status', 'employment_type',
                AllowedFilter::exact('organization_id'),
                AllowedFilter::exact('department_id'),
                AllowedFilter::partial('search', 'first_name'),
            )
            ->allowedSorts('last_name', 'employee_number', 'hire_date', 'created_at')
            ->defaultSort('last_name')
            ->when(
                ! $user->hasCentralAccess(),
                fn ($query) => $query->where('organization_id', $user->employee?->organization_id)
            )
            ->when(
                $user->hasRole('department-manager'),
                fn ($query) => $query->where('department_id', $user->employee?->department_id)
            )
            ->paginate(request()->integer('per_page', 15));

        return $this->success(
            EmployeeResource::collection($employees),
            meta: $this->paginationMeta($employees)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request, CreateEmployeeAction $action): JsonResponse
    {
        $employee = $action->handle($request->validated());

        return $this->success(new EmployeeResource($employee), 'Employee created.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);

        $employee->load(['organization', 'department', 'position']);

        return $this->success(new EmployeeResource($employee));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $oldValues = $employee->makeHidden(['passport_number', 'pinfl'])->toArray();

        $employee->update($request->validated());

        $this->auditLog->log(
            'updated',
            'employees',
            $employee,
            $oldValues,
            $employee->makeHidden(['passport_number', 'pinfl'])->toArray()
        );

        return $this->success(new EmployeeResource($employee), 'Employee updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        Gate::authorize('delete', $employee);

        $oldValues = $employee->makeHidden(['passport_number', 'pinfl'])->toArray();

        $employee->delete();

        $this->auditLog->log('deleted', 'employees', $employee, oldValues: $oldValues);

        return $this->success(message: 'Employee deleted.');
    }
}
