<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Attendance\CompareDevicePeople;
use App\Http\Controllers\Controller;
use App\Http\Requests\LinkDahuaPersonRequest;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Terminal-enrolled people against employees, and linking the two through
 * `employees.dahua_person_id` — what makes a person's scans count as that
 * employee's attendance.
 */
class AttendanceDevicePeopleController extends Controller
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    public function index(CompareDevicePeople $compare): JsonResponse
    {
        Gate::authorize('viewAny', AttendanceDevice::class);

        return $this->success($compare->handle());
    }

    public function link(LinkDahuaPersonRequest $request): JsonResponse
    {
        $employee = Employee::findOrFail($request->integer('employee_id'));
        $oldValue = $employee->dahua_person_id;

        $employee->update(['dahua_person_id' => $request->string('user_id')->toString()]);
        $this->auditLog->log('updated', 'employees', $employee,
            oldValues: ['dahua_person_id' => $oldValue],
            newValues: ['dahua_person_id' => $employee->dahua_person_id],
        );

        return $this->success(['employee_id' => $employee->id, 'dahua_person_id' => $employee->dahua_person_id], 'Bog\'landi.');
    }

    public function unlink(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', AttendanceDevice::class);
        $request->validate(['employee_id' => ['required', 'integer', 'exists:employees,id']]);

        $employee = Employee::findOrFail($request->integer('employee_id'));
        $oldValue = $employee->dahua_person_id;

        $employee->update(['dahua_person_id' => null]);
        $this->auditLog->log('updated', 'employees', $employee,
            oldValues: ['dahua_person_id' => $oldValue],
            newValues: ['dahua_person_id' => null],
        );

        return $this->success(['employee_id' => $employee->id], 'Bog\'lanish bekor qilindi.');
    }
}
