<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessTripStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessTripRequest;
use App\Http\Requests\UpdateBusinessTripRequest;
use App\Http\Resources\Api\V1\BusinessTripResource;
use App\Models\BusinessTrip;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BusinessTripController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Display a listing of the resource: always includes the caller's
     * own trips, plus every trip in scope for `business_trips.manage`
     * holders.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', BusinessTrip::class);

        $user = request()->user();
        $employee = $user->employee;

        $trips = QueryBuilder::for(BusinessTrip::class)
            ->with('employee')
            ->allowedFilters('status', AllowedFilter::exact('employee_id'))
            ->defaultSort('-start_date')
            ->where(function ($query) use ($user, $employee) {
                $query->where('employee_id', $employee?->id ?? 0);

                if ($user->can('business_trips.manage') && $user->hasCentralAccess()) {
                    $query->orWhereNotNull('id');
                } elseif ($user->can('business_trips.manage')) {
                    $query->orWhereHas('employee', function ($employeeQuery) use ($user) {
                        $employeeQuery->where('organization_id', $user->employee?->organization_id);

                        if ($user->hasRole('department-manager')) {
                            $employeeQuery->where('department_id', $user->employee?->department_id);
                        }
                    });
                }
            })
            ->paginate(request()->integer('per_page', 15));

        return $this->success(BusinessTripResource::collection($trips), meta: $this->paginationMeta($trips));
    }

    /**
     * Current and upcoming trips for the dashboard widget — the
     * authenticated employee's own only.
     */
    public function upcoming(): JsonResponse
    {
        $employee = request()->user()->employee;

        if (! $employee) {
            return $this->success([]);
        }

        $trips = BusinessTrip::query()
            ->where('employee_id', $employee->id)
            ->where('status', BusinessTripStatus::Scheduled)
            ->where('end_date', '>=', now()->toDateString())
            ->orderBy('start_date')
            ->limit(5)
            ->get();

        return $this->success(BusinessTripResource::collection($trips));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBusinessTripRequest $request): JsonResponse
    {
        $data = $request->validated();

        $trip = BusinessTrip::create([
            ...$data,
            'status' => BusinessTripStatus::Scheduled,
            'created_by' => $request->user()->id,
        ]);

        $this->auditLog->log('created', 'business_trips', $trip, newValues: $data);

        return $this->success(new BusinessTripResource($trip->load('employee')), 'Xizmat safari yaratildi.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(BusinessTrip $businessTrip): JsonResponse
    {
        Gate::authorize('view', $businessTrip);

        return $this->success(new BusinessTripResource($businessTrip->load('employee')));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBusinessTripRequest $request, BusinessTrip $businessTrip): JsonResponse
    {
        $data = $request->validated();
        $businessTrip->update($data);

        $this->auditLog->log('updated', 'business_trips', $businessTrip, newValues: $data);

        return $this->success(new BusinessTripResource($businessTrip->load('employee')), 'Xizmat safari yangilandi.');
    }

    /**
     * Cancel the trip — no hard-delete endpoint exists.
     */
    public function cancel(BusinessTrip $businessTrip): JsonResponse
    {
        Gate::authorize('manage', $businessTrip);

        if ($businessTrip->status === BusinessTripStatus::Cancelled) {
            return $this->error('Bu xizmat safari allaqachon bekor qilingan.', 409);
        }

        $businessTrip->update(['status' => BusinessTripStatus::Cancelled]);

        $this->auditLog->log('cancelled', 'business_trips', $businessTrip);

        return $this->success(new BusinessTripResource($businessTrip->load('employee')), 'Xizmat safari bekor qilindi.');
    }
}
