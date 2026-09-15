<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Profile\ApproveChangeRequestAction;
use App\Enums\ChangeRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectRequest;
use App\Http\Resources\Api\V1\EmployeeChangeRequestResource;
use App\Models\EmployeeChangeRequest;
use App\Notifications\ProfileChangeRejected;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ChangeRequestController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Display a listing of the resource: "my requests" for a plain
     * employee, the (scoped) review queue for HR/admin roles.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', EmployeeChangeRequest::class);

        $user = request()->user();

        $changeRequests = QueryBuilder::for(EmployeeChangeRequest::class)
            ->with('employee')
            ->allowedFilters('status', AllowedFilter::exact('employee_id'))
            ->defaultSort('-created_at')
            ->when(
                ! $user->can('employees.update'),
                fn ($query) => $query->where('employee_id', $user->employee?->id)
            )
            ->when(
                $user->can('employees.update') && ! $user->hasCentralAccess(),
                fn ($query) => $query->whereHas('employee', function ($employeeQuery) use ($user) {
                    $employeeQuery->where('organization_id', $user->employee?->organization_id);

                    if ($user->hasRole('department-manager')) {
                        $employeeQuery->where('department_id', $user->employee?->department_id);
                    }
                })
            )
            ->paginate(request()->integer('per_page', 15));

        return $this->success(
            EmployeeChangeRequestResource::collection($changeRequests),
            meta: $this->paginationMeta($changeRequests)
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(EmployeeChangeRequest $changeRequest): JsonResponse
    {
        Gate::authorize('view', $changeRequest);

        return $this->success(new EmployeeChangeRequestResource($changeRequest->load('employee')));
    }

    /**
     * Approve the request and apply its changes to the employee record.
     */
    public function approve(EmployeeChangeRequest $changeRequest, ApproveChangeRequestAction $action): JsonResponse
    {
        Gate::authorize('review', $changeRequest);

        if ($changeRequest->status !== ChangeRequestStatus::Pending) {
            return $this->error('Bu so\'rov allaqachon ko\'rib chiqilgan.', 409);
        }

        $oldValues = $changeRequest->changes;

        $changeRequest = $action->handle($changeRequest, request()->user());

        $this->auditLog->log('approved', 'employees', $changeRequest, $oldValues, $changeRequest->changes);

        return $this->success(new EmployeeChangeRequestResource($changeRequest), 'So\'rov tasdiqlandi.');
    }

    /**
     * Reject the request; the employee record is left untouched.
     */
    public function reject(RejectRequest $request, EmployeeChangeRequest $changeRequest): JsonResponse
    {
        Gate::authorize('review', $changeRequest);

        if ($changeRequest->status !== ChangeRequestStatus::Pending) {
            return $this->error('Bu so\'rov allaqachon ko\'rib chiqilgan.', 409);
        }

        $changeRequest->update([
            'status' => ChangeRequestStatus::Rejected,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_comment' => $request->string('reason')->toString(),
        ]);

        $changeRequest->employee->user?->notify(new ProfileChangeRejected($changeRequest));

        $this->auditLog->log('rejected', 'employees', $changeRequest);

        return $this->success(new EmployeeChangeRequestResource($changeRequest), 'So\'rov rad etildi.');
    }
}
