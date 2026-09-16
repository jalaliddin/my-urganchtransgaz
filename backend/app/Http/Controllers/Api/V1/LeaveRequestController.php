<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\LeaveRequests\ApproveLeaveRequest;
use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectRequest;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Resources\Api\V1\LeaveRequestResource;
use App\Models\Department;
use App\Models\LeaveRequest;
use App\Notifications\LeaveRequestApproved;
use App\Notifications\LeaveRequestDepartmentApproved;
use App\Notifications\LeaveRequestRejected;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LeaveRequestController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Display a listing of the resource: always includes the caller's
     * own requests, plus the scoped review queue for department-manager/
     * HR/admin roles — an employee who also reviews others' requests
     * still needs to see their own submissions in the same list.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', LeaveRequest::class);

        $user = request()->user();
        $employee = $user->employee;

        $leaveRequests = QueryBuilder::for(LeaveRequest::class)
            ->with(['employee', 'departmentReviewer', 'hrReviewer'])
            ->allowedFilters('status', 'type', AllowedFilter::exact('employee_id'))
            ->defaultSort('-created_at')
            ->where(function ($query) use ($user, $employee) {
                $query->where('employee_id', $employee?->id ?? 0);

                $canReview = $user->can('leave_requests.review') || $user->can('leave_requests.approve');

                if ($canReview && $user->hasCentralAccess()) {
                    $query->orWhereNotNull('id');
                } elseif ($canReview) {
                    $query->orWhereHas('employee', function ($employeeQuery) use ($user) {
                        $employeeQuery->where('organization_id', $user->employee?->organization_id);

                        if ($user->hasRole('department-manager')) {
                            $employeeQuery->where('department_id', $user->employee?->department_id);
                        }
                    });
                }
            })
            ->paginate(request()->integer('per_page', 15));

        return $this->success(LeaveRequestResource::collection($leaveRequests), meta: $this->paginationMeta($leaveRequests));
    }

    /**
     * Store a newly created resource in storage. Always targets the
     * requester's own employee record. Starts at `department_approved`
     * directly when the employee's department has no manager to perform
     * stage 1 — computed once here, not re-checked on every action.
     */
    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $employee = $request->user()->employee;
        $data = $request->validated();

        $department = $employee->department_id ? Department::find($employee->department_id) : null;
        $initialStatus = ($department && $department->manager_id)
            ? LeaveRequestStatus::Pending
            : LeaveRequestStatus::DepartmentApproved;

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'reason' => $data['reason'] ?? null,
            'status' => $initialStatus,
        ]);

        $this->auditLog->log('created', 'leave_requests', $leaveRequest, newValues: $data);

        return $this->success(new LeaveRequestResource($leaveRequest->load('employee')), 'So\'rov yuborildi.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        Gate::authorize('view', $leaveRequest);

        return $this->success(new LeaveRequestResource(
            $leaveRequest->load(['employee', 'departmentReviewer', 'hrReviewer'])
        ));
    }

    /**
     * Stage 1 — the employee's own department manager approves.
     */
    public function departmentApprove(LeaveRequest $leaveRequest): JsonResponse
    {
        Gate::authorize('departmentReview', $leaveRequest);

        $leaveRequest->update([
            'status' => LeaveRequestStatus::DepartmentApproved,
            'department_reviewed_by' => request()->user()->id,
            'department_reviewed_at' => now(),
        ]);

        $leaveRequest->employee->user?->notify(new LeaveRequestDepartmentApproved($leaveRequest));

        $this->auditLog->log('department_approved', 'leave_requests', $leaveRequest);

        return $this->success(new LeaveRequestResource($leaveRequest->load('employee')), 'So\'rov bo\'lim rahbari tomonidan tasdiqlandi.');
    }

    /**
     * Stage 1 rejection.
     */
    public function departmentReject(RejectRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        Gate::authorize('departmentReview', $leaveRequest);

        $leaveRequest->update([
            'status' => LeaveRequestStatus::Rejected,
            'department_reviewed_by' => $request->user()->id,
            'department_reviewed_at' => now(),
            'rejection_reason' => $request->string('reason')->toString(),
        ]);

        $leaveRequest->employee->user?->notify(new LeaveRequestRejected($leaveRequest));

        $this->auditLog->log('rejected', 'leave_requests', $leaveRequest);

        return $this->success(new LeaveRequestResource($leaveRequest->load('employee')), 'So\'rov rad etildi.');
    }

    /**
     * Stage 2 (final) — HR/organization-admin/central approves. Computes
     * and applies the Employee.status effect immediately if the range
     * already covers today.
     */
    public function approve(LeaveRequest $leaveRequest, ApproveLeaveRequest $action): JsonResponse
    {
        Gate::authorize('approve', $leaveRequest);

        $leaveRequest = $action->handle($leaveRequest, request()->user());

        $leaveRequest->employee->user?->notify(new LeaveRequestApproved($leaveRequest));

        $this->auditLog->log('approved', 'leave_requests', $leaveRequest);

        return $this->success(new LeaveRequestResource($leaveRequest->load('employee')), 'So\'rov to\'liq tasdiqlandi.');
    }

    /**
     * Stage 2 (final) rejection.
     */
    public function reject(RejectRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        Gate::authorize('approve', $leaveRequest);

        $leaveRequest->update([
            'status' => LeaveRequestStatus::Rejected,
            'hr_reviewed_by' => $request->user()->id,
            'hr_reviewed_at' => now(),
            'rejection_reason' => $request->string('reason')->toString(),
        ]);

        $leaveRequest->employee->user?->notify(new LeaveRequestRejected($leaveRequest));

        $this->auditLog->log('rejected', 'leave_requests', $leaveRequest);

        return $this->success(new LeaveRequestResource($leaveRequest->load('employee')), 'So\'rov rad etildi.');
    }

    /**
     * An employee cancels their own still-in-flight request — no hard
     * delete exists.
     */
    public function cancel(LeaveRequest $leaveRequest): JsonResponse
    {
        Gate::authorize('cancel', $leaveRequest);

        $leaveRequest->update(['status' => LeaveRequestStatus::Cancelled]);

        $this->auditLog->log('cancelled', 'leave_requests', $leaveRequest);

        return $this->success(new LeaveRequestResource($leaveRequest->load('employee')), 'So\'rov bekor qilindi.');
    }
}
