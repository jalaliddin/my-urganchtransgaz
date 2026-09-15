<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\Api\V1\DepartmentResource;
use App\Models\Department;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class DepartmentController extends Controller
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
        Gate::authorize('viewAny', Department::class);

        $user = request()->user();

        $departments = QueryBuilder::for(Department::class)
            ->with(['organization'])
            ->withCount('employees')
            ->allowedFilters(
                'status',
                AllowedFilter::exact('organization_id'),
                AllowedFilter::partial('search', 'name'),
            )
            ->allowedSorts('name', 'code', 'created_at')
            ->defaultSort('name')
            ->when(
                ! $user->hasCentralAccess(),
                fn ($query) => $query->where('organization_id', $user->employee?->organization_id)
            )
            ->when(
                $user->hasRole('department-manager'),
                fn ($query) => $query->where('id', $user->employee?->department_id)
            )
            ->paginate(request()->integer('per_page', 15));

        return $this->success(
            DepartmentResource::collection($departments),
            meta: $this->paginationMeta($departments)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated())->refresh();

        $this->auditLog->log('created', 'departments', $department, newValues: $department->toArray());

        return $this->success(new DepartmentResource($department), 'Department created.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Department $department): JsonResponse
    {
        Gate::authorize('view', $department);

        $department->load(['organization', 'manager'])->loadCount('employees');

        return $this->success(new DepartmentResource($department));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $oldValues = $department->toArray();

        $department->update($request->validated());

        $this->auditLog->log('updated', 'departments', $department, $oldValues, $department->toArray());

        return $this->success(new DepartmentResource($department), 'Department updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Department $department): JsonResponse
    {
        Gate::authorize('delete', $department);

        $oldValues = $department->toArray();

        $department->delete();

        $this->auditLog->log('deleted', 'departments', $department, oldValues: $oldValues);

        return $this->success(message: 'Department deleted.');
    }
}
