<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Actions\Attendance\RecordCheckInAction;
use App\Actions\Attendance\RecordCheckOutAction;
use App\Enums\AttendanceSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordAttendanceEventRequest;
use App\Http\Resources\Api\V1\AttendanceRecordResource;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class AttendanceEventController extends Controller
{
    public function __construct(
        private RecordCheckInAction $checkInAction,
        private RecordCheckOutAction $checkOutAction,
    ) {
        //
    }

    /**
     * Record a biometric device event. The device is already authenticated
     * by AuthenticateAttendanceDevice; the employee is always resolved
     * server-side from `employee_number`, never trusted as a raw id from
     * the payload.
     */
    public function store(RecordAttendanceEventRequest $request): JsonResponse
    {
        /** @var AttendanceDevice $device */
        $device = $request->attributes->get('attendanceDevice');

        if ($request->string('device_id')->toString() !== $device->device_id) {
            return $this->error('device_id does not match the authenticated device.', 422);
        }

        $employee = Employee::where('employee_number', $request->string('employee_number')->toString())->firstOrFail();
        $eventTime = Carbon::parse($request->string('event_time')->toString());

        $record = match ($request->string('event_type')->toString()) {
            'check_in' => $this->checkInAction->handle($employee, $eventTime, AttendanceSource::Biometric),
            'check_out' => $this->checkOutAction->handle($employee, $eventTime, AttendanceSource::Biometric),
        };

        return $this->success(new AttendanceRecordResource($record), 'Event recorded.', 201);
    }
}
