<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Employees\CreateEmployeeAction;
use App\Actions\Export\ExportRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Resources\Api\V1\EmployeeResource;
use App\Models\Employee;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
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
        Gate::authorize('viewAny', Employee::class);

        $user = request()->user();

        $query = QueryBuilder::for(Employee::class)
            ->with(['organization', 'department', 'position'])
            ->allowedFilters(
                'status', 'employment_type',
                AllowedFilter::exact('organization_id'),
                AllowedFilter::exact('department_id'),
                AllowedFilter::partial('search', 'first_name'),
            )
            ->allowedSorts('last_name', 'employee_number', 'hire_date', 'created_at')
            ->defaultSort('last_name');

        // Plain if, never ->when(): Spatie's QueryBuilder forwards
        // unrecognized methods (when() included) to the underlying
        // Eloquent builder, so a ->when() callback's return value
        // silently downgrades the chain away from QueryBuilder — this
        // must stay the real wrapper so getEloquentBuilder() below is
        // reliable regardless of which branches actually ran.
        if (! $user->hasCentralAccess()) {
            $query->where('organization_id', $user->employee?->organization_id);
        }

        if ($user->hasRole('department-manager')) {
            $query->where('department_id', $user->employee?->department_id);
        }

        if ($format = request()->string('export')->toString()) {
            return $export->stream($query->getEloquentBuilder(), [
                'employee_number' => 'Tabel raqami',
                'last_name' => 'Familiyasi',
                'first_name' => 'Ismi',
                'organization.name' => 'Tashkilot',
                'department.name' => 'Bo\'lim',
                'phone' => 'Telefon',
                'status' => 'Holat',
            ], $format, 'employees');
        }

        $employees = $query->paginate(request()->integer('per_page', 15));

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
     * Stream the employee's profile photo. Photos live on the private
     * disk like documents do, so this authorized endpoint is the only way
     * to fetch one — never a public URL.
     */
    public function photo(Employee $employee): StreamedResponse|JsonResponse
    {
        Gate::authorize('view', $employee);

        if (! $employee->photo || ! Storage::disk('local')->exists($employee->photo)) {
            return $this->error('Rasm topilmadi.', 404);
        }

        return Storage::disk('local')->response($employee->photo);
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

    /**
     * Change the linked user account's role — the only place a role is
     * ever changed after initial account creation (CreateEmployeeAction
     * assigns the first one). Audit-logged as "permission_changed" per
     * Module 19's own action list.
     */
    public function updateRole(UpdateUserRoleRequest $request, Employee $employee): JsonResponse
    {
        abort_unless($employee->user_id, 404);

        $user = $employee->user;
        $oldRole = $user->getRoleNames()->first();
        $newRole = $request->validated('role');

        $user->syncRoles([$newRole]);

        $this->auditLog->log('permission_changed', 'users', $user, ['role' => $oldRole], ['role' => $newRole]);

        return $this->success(message: 'Xodim roli yangilandi.');
    }
}
