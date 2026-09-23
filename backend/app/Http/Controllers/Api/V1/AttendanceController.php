<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Attendance\BuildTimesheet;
use App\Actions\Attendance\CalculateAttendanceStatus;
use App\Actions\Attendance\RecordCheckInAction;
use App\Actions\Attendance\RecordCheckOutAction;
use App\Actions\Export\ExportRecords;
use App\Actions\Export\ExportTimesheet;
use App\Enums\AttendanceSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Http\Resources\Api\V1\AttendanceEventResource;
use App\Http\Resources\Api\V1\AttendanceRecordResource;
use App\Http\Resources\Api\V1\TodayAttendanceResource;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

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

        $this->attachVisitsCounts($records);

        return $this->success(
            AttendanceRecordResource::collection($records),
            meta: $this->paginationMeta($records)
        );
    }

    /**
     * Attach each record's "necha bora kirib chiqqan" (how many scans
     * that day) as a transient visits_count attribute, via one grouped
     * query for the whole page instead of one per record.
     */
    private function attachVisitsCounts(LengthAwarePaginator $records): void
    {
        if ($records->isEmpty()) {
            return;
        }

        $dates = $records->getCollection()->pluck('date')->map->toDateString();

        $counts = AttendanceEvent::query()
            ->whereIn('employee_id', $records->getCollection()->pluck('employee_id')->unique())
            ->whereDate('occurred_at', '>=', $dates->min())
            ->whereDate('occurred_at', '<=', $dates->max())
            ->selectRaw('employee_id, DATE(occurred_at) as day, COUNT(*) as cnt')
            ->groupBy('employee_id', 'day')
            ->get()
            ->keyBy(fn ($row) => $row->employee_id.'|'.$row->day);

        foreach ($records as $record) {
            $key = $record->employee_id.'|'.$record->date->toDateString();
            $record->visits_count = (int) ($counts[$key]->cnt ?? 0);
        }
    }

    /**
     * The raw scan log behind one daily record — every individual
     * check-in/check-out event that day, not just the summarized
     * check_in/check_out on the record itself.
     */
    public function events(AttendanceRecord $attendanceRecord): JsonResponse
    {
        Gate::authorize('view', $attendanceRecord);

        $events = AttendanceEvent::where('employee_id', $attendanceRecord->employee_id)
            ->whereDate('occurred_at', $attendanceRecord->date)
            ->orderBy('occurred_at')
            ->get();

        return $this->success(AttendanceEventResource::collection($events));
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
    public function report(ExportRecords $export): JsonResponse|Response
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

        $query = AttendanceRecord::query()
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
            // "Keldi" (present, on time) is its own count, not just "total_days
            // minus everything else" — every status below is mutually
            // exclusive per record, so these all sum back to total_days.
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'early_leave' THEN 1 ELSE 0 END) as early_leave_count")
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'business_trip' THEN 1 ELSE 0 END) as business_trip_count")
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'vacation' THEN 1 ELSE 0 END) as vacation_count")
            ->selectRaw("SUM(CASE WHEN attendance_records.status = 'sick_leave' THEN 1 ELSE 0 END) as sick_leave_count")
            ->groupBy('group_id', 'label')
            ->orderBy('label');

        if ($format = request()->string('export')->toString()) {
            return $export->stream($query, [
                'label' => 'Nomi',
                'total_days' => 'Kunlar soni',
                'total_worked_minutes' => 'Ishlangan (daqiqa)',
                'present_count' => 'Keldi',
                'late_count' => 'Kech keldi',
                'early_leave_count' => 'Erta ketdi',
                'absent_count' => 'Kelmadi',
                'business_trip_count' => 'Xizmat safarida',
                'vacation_count' => 'Ta\'tilda',
                'sick_leave_count' => 'Bemor varaqasida',
            ], $format, 'attendance-report');
        }

        $rows = $query->get()->map(fn ($row) => [
            'group_id' => (int) $row->group_id,
            'label' => $row->label,
            'total_days' => (int) $row->total_days,
            'total_worked_minutes' => (int) $row->total_worked_minutes,
            'present_count' => (int) $row->present_count,
            'late_count' => (int) $row->late_count,
            'absent_count' => (int) $row->absent_count,
            'early_leave_count' => (int) $row->early_leave_count,
            'business_trip_count' => (int) $row->business_trip_count,
            'vacation_count' => (int) $row->vacation_count,
            'sick_leave_count' => (int) $row->sick_leave_count,
        ]);

        return $this->success($rows, meta: [
            'group_by' => $groupBy,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    /**
     * The monthly "tabel": one row per employee, one column per calendar
     * day, each cell showing that day's status (present/late/absent/...)
     * and worked minutes — the batafsil (detailed), day-by-day view a
     * summary report can't show, and the printable timesheet form this
     * company's HR process expects on top of the aggregate report above.
     * Same visibility rules as `today()`: central-access roles see every
     * employee (optionally narrowed by organization/department/employee),
     * a department-manager sees their own department, everyone else sees
     * only themselves.
     */
    public function timesheet(BuildTimesheet $buildTimesheet, ExportTimesheet $export): JsonResponse|Response
    {
        Gate::authorize('viewAny', AttendanceRecord::class);

        $user = request()->user();
        $month = request()->filled('month') ? Carbon::parse(request()->string('month')->toString().'-01') : now()->startOfMonth();
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $employees = Employee::query()
            ->with('department')
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
            ->when(request()->filled('organization_id'), fn ($query) => $query->where('organization_id', request()->integer('organization_id')))
            ->when(request()->filled('department_id'), fn ($query) => $query->where('department_id', request()->integer('department_id')))
            ->when(request()->filled('employee_id'), fn ($query) => $query->where('id', request()->integer('employee_id')))
            ->orderBy('last_name')
            ->get();

        $workingDays = Setting::get('attendance.working_days', [1, 2, 3, 4, 5]);
        $timesheet = $buildTimesheet->handle($employees, $from, $to, $workingDays);

        if ($format = request()->string('export')->toString()) {
            return $export->stream($timesheet, $format, 'tabel-'.$from->format('Y-m'));
        }

        return $this->success($timesheet);
    }
}
