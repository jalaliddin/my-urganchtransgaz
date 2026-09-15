<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Attendance\CalculateAttendanceStatus;
use App\Actions\Attendance\RecordCheckInAction;
use App\Actions\Attendance\RecordCheckOutAction;
use App\Enums\AttendanceSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Resources\Api\V1\AttendanceRecordResource;
use App\Http\Resources\Api\V1\TodayAttendanceResource;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AttendanceController extends Controller
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
        Gate::authorize('viewAny', AttendanceRecord::class);

        $user = request()->user();

        $records = QueryBuilder::for(AttendanceRecord::class)
            ->with('employee')
            ->allowedFilters(
                'status',
                AllowedFilter::exact('employee_id'),
            )
            ->when(request()->filled('from'), fn ($query) => $query->whereDate('date', '>=', request()->date('from')))
            ->when(request()->filled('to'), fn ($query) => $query->whereDate('date', '<=', request()->date('to')))
            ->defaultSort('-date')
            ->when(
                ! $user->can('attendance.view'),
                fn ($query) => $query->where('employee_id', $user->employee?->id)
            )
            ->when(
                $user->can('attendance.view') && ! $user->hasCentralAccess(),
                fn ($query) => $query->whereHas('employee', function ($employeeQuery) use ($user) {
                    $employeeQuery->where('organization_id', $user->employee?->organization_id);

                    if ($user->hasRole('department-manager')) {
                        $employeeQuery->where('department_id', $user->employee?->department_id);
                    }
                })
            )
            ->paginate(request()->integer('per_page', 15));

        return $this->success(
            AttendanceRecordResource::collection($records),
            meta: $this->paginationMeta($records)
        );
    }

    /**
     * The scoped "who's here today" board — built from Employee, not
     * attendance_records, so an employee absent all day (no row yet) still
     * shows up instead of silently disappearing from the list.
     */
    public function today(): JsonResponse
    {
        Gate::authorize('viewAny', AttendanceRecord::class);

        $user = request()->user();

        $employees = Employee::query()
            ->with(['department', 'todayAttendance'])
            ->when(
                ! $user->can('attendance.view'),
                fn ($query) => $query->where('id', $user->employee?->id)
            )
            ->when(
                $user->can('attendance.view') && ! $user->hasCentralAccess(),
                fn ($query) => $query->where('organization_id', $user->employee?->organization_id)
                    ->when(
                        $user->hasRole('department-manager'),
                        fn ($departmentQuery) => $departmentQuery->where('department_id', $user->employee?->department_id)
                    )
            )
            ->orderBy('last_name')
            ->get();

        return $this->success(TodayAttendanceResource::collection($employees));
    }

    /**
     * Record a check-in for the authenticated user's own employee record.
     */
    public function checkIn(Request $request, RecordCheckInAction $action): JsonResponse
    {
        $employee = $request->user()->employee()->firstOrFail();
        $today = now()->toDateString();

        $alreadyCheckedIn = AttendanceRecord::where('employee_id', $employee->id)
            ->where('date', $today)
            ->whereNotNull('check_in')
            ->exists();

        if ($alreadyCheckedIn) {
            return $this->error('Siz bugun allaqachon kelganingizni qayd etdingiz.', 409);
        }

        $record = $action->handle($employee, now(), AttendanceSource::Web);

        $this->auditLog->log('checked_in', 'attendance', $record);

        return $this->success(new AttendanceRecordResource($record), 'Kelganingiz qayd etildi.');
    }

    /**
     * Record a check-out for the authenticated user's own employee record.
     */
    public function checkOut(Request $request, RecordCheckOutAction $action): JsonResponse
    {
        $employee = $request->user()->employee()->firstOrFail();
        $today = now()->toDateString();

        $record = AttendanceRecord::where('employee_id', $employee->id)->where('date', $today)->first();

        if (! $record || ! $record->check_in) {
            return $this->error('Avval kelganingizni qayd eting.', 409);
        }

        if ($record->check_out) {
            return $this->error('Siz bugun allaqachon ketganingizni qayd etdingiz.', 409);
        }

        $record = $action->handle($employee, now(), AttendanceSource::Web);

        $this->auditLog->log('checked_out', 'attendance', $record);

        return $this->success(new AttendanceRecordResource($record), 'Ketganingiz qayd etildi.');
    }

    /**
     * Store a manually-entered/corrected attendance record (HR/admin
     * backfill) in storage.
     */
    public function store(StoreAttendanceRequest $request, CalculateAttendanceStatus $calculateStatus): JsonResponse
    {
        $data = $request->validated();

        $checkInAt = $data['check_in'] ? Carbon::parse("{$data['date']} {$data['check_in']}") : null;
        $checkOutAt = $data['check_out'] ? Carbon::parse("{$data['date']} {$data['check_out']}") : null;

        $record = AttendanceRecord::create([
            'employee_id' => $data['employee_id'],
            'date' => $data['date'],
            'check_in' => $checkInAt,
            'check_out' => $checkOutAt,
            'worked_minutes' => $checkInAt && $checkOutAt ? (int) abs($checkInAt->diffInMinutes($checkOutAt)) : null,
            'status' => $data['status'] ?? $calculateStatus->handle($checkInAt, $checkOutAt),
            'source' => AttendanceSource::Manual,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->auditLog->log('created', 'attendance', $record, newValues: $data);

        return $this->success(new AttendanceRecordResource($record), 'Davomat yozuvi qo\'shildi.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(AttendanceRecord $attendanceRecord): JsonResponse
    {
        Gate::authorize('view', $attendanceRecord);

        $attendanceRecord->load('employee');

        return $this->success(new AttendanceRecordResource($attendanceRecord));
    }

    /**
     * Update the specified resource in storage (a manual correction).
     */
    public function update(UpdateAttendanceRequest $request, AttendanceRecord $attendanceRecord, CalculateAttendanceStatus $calculateStatus): JsonResponse
    {
        $data = $request->validated();
        $date = $attendanceRecord->date->toDateString();

        $checkInAt = $data['check_in'] ? Carbon::parse("{$date} {$data['check_in']}") : null;
        $checkOutAt = $data['check_out'] ? Carbon::parse("{$date} {$data['check_out']}") : null;

        $attendanceRecord->update([
            'check_in' => $checkInAt,
            'check_out' => $checkOutAt,
            'worked_minutes' => $checkInAt && $checkOutAt ? (int) abs($checkInAt->diffInMinutes($checkOutAt)) : null,
            'status' => $data['status'] ?? $calculateStatus->handle($checkInAt, $checkOutAt),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->auditLog->log('updated', 'attendance', $attendanceRecord, newValues: $data);

        return $this->success(new AttendanceRecordResource($attendanceRecord), 'Davomat yozuvi yangilandi.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AttendanceRecord $attendanceRecord): JsonResponse
    {
        Gate::authorize('delete', $attendanceRecord);

        $this->auditLog->log('deleted', 'attendance', $attendanceRecord);

        $attendanceRecord->delete();

        return $this->success(message: 'Davomat yozuvi o\'chirildi.');
    }

    /**
     * Database-aggregated attendance report, grouped by employee,
     * department, or organization over a date range. Aggregation happens
     * entirely in SQL — never loads the underlying records into memory.
     */
    public function report(): JsonResponse
    {
        Gate::authorize('viewAny', AttendanceRecord::class);

        $user = request()->user();
        $groupBy = request()->string('group_by', 'employee')->toString();

        if (! in_array($groupBy, ['employee', 'department', 'organization'], true)) {
            return $this->error('Invalid group_by; expected employee, department, or organization.', 422);
        }

        $from = request()->filled('from') ? request()->date('from') : now()->startOfMonth();
        $to = request()->filled('to') ? request()->date('to') : now();

        [$groupColumn, $labelColumn] = match ($groupBy) {
            'employee' => ['employees.id', "CONCAT(employees.last_name, ' ', employees.first_name)"],
            'department' => ['departments.id', 'departments.name'],
            'organization' => ['organizations.id', 'organizations.name'],
        };

        $rows = AttendanceRecord::query()
            ->join('employees', 'employees.id', '=', 'attendance_records.employee_id')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->join('organizations', 'organizations.id', '=', 'employees.organization_id')
            ->whereBetween('attendance_records.date', [$from->toDateString(), $to->toDateString()])
            ->when(request()->filled('employee_id'), fn ($query) => $query->where('employees.id', request()->integer('employee_id')))
            ->when(request()->filled('department_id'), fn ($query) => $query->where('employees.department_id', request()->integer('department_id')))
            ->when(request()->filled('organization_id'), fn ($query) => $query->where('employees.organization_id', request()->integer('organization_id')))
            ->when(
                ! $user->hasCentralAccess(),
                function ($query) use ($user) {
                    $query->where('employees.organization_id', $user->employee?->organization_id);

                    if ($user->hasRole('department-manager')) {
                        $query->where('employees.department_id', $user->employee?->department_id);
                    }
                }
            )
            ->selectRaw("{$groupColumn} as group_id")
            ->selectRaw("{$labelColumn} as label")
            ->selectRaw('COUNT(DISTINCT attendance_records.date) as total_days')
            ->selectRaw('COALESCE(SUM(attendance_records.worked_minutes), 0) as total_worked_minutes')
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'early_leave' THEN 1 ELSE 0 END) as early_leave_count")
            ->groupBy('group_id', 'label')
            ->orderBy('label')
            ->get()
            ->map(fn ($row) => [
                'group_id' => (int) $row->group_id,
                'label' => $row->label,
                'total_days' => (int) $row->total_days,
                'total_worked_minutes' => (int) $row->total_worked_minutes,
                'late_count' => (int) $row->late_count,
                'absent_count' => (int) $row->absent_count,
                'early_leave_count' => (int) $row->early_leave_count,
            ]);

        return $this->success($rows, meta: [
            'group_by' => $groupBy,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }
}
