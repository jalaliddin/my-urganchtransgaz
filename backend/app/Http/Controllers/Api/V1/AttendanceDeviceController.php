<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Attendance\IssueAttendanceDeviceToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceDeviceRequest;
use App\Http\Requests\UpdateAttendanceDeviceRequest;
use App\Http\Resources\Api\V1\AttendanceDeviceResource;
use App\Models\AttendanceDevice;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Turnstiles/terminals that post attendance through the device webhook
 * (the Dahua Chrome extension or bridge). What used to need
 * `php artisan attendance:create-device` on the server.
 */
class AttendanceDeviceController extends Controller
{
    public function __construct(
        private AuditLogService $auditLog,
        private IssueAttendanceDeviceToken $issueToken,
    ) {
        //
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', AttendanceDevice::class);

        $devices = AttendanceDevice::with('organization')->orderBy('device_id')->get();

        return $this->success(AttendanceDeviceResource::collection($devices));
    }

    /**
     * The response carries the device's token once, as `token`.
     */
    public function store(StoreAttendanceDeviceRequest $request): JsonResponse
    {
        $device = new AttendanceDevice([...$request->validated(), 'is_active' => true]);
        $token = $this->issueToken->handle($device);

        $this->auditLog->log('created', 'attendance_devices', $device, newValues: $device->only('device_id', 'name', 'organization_id'));

        return $this->success([
            'device' => new AttendanceDeviceResource($device->refresh()->load('organization')),
            'token' => $token,
        ], 'Qurilma qo\'shildi.', 201);
    }

    public function update(UpdateAttendanceDeviceRequest $request, AttendanceDevice $attendanceDevice): JsonResponse
    {
        $oldValues = $attendanceDevice->only('name', 'organization_id', 'is_active');
        $attendanceDevice->update($request->validated());

        $this->auditLog->log('updated', 'attendance_devices', $attendanceDevice,
            oldValues: $oldValues,
            newValues: $attendanceDevice->only('name', 'organization_id', 'is_active'),
        );

        return $this->success(new AttendanceDeviceResource($attendanceDevice->load('organization')));
    }

    /**
     * A new token for a lost one or one that may have leaked; the old one
     * stops working at once. Returned once, as `token`.
     */
    public function regenerateToken(AttendanceDevice $attendanceDevice): JsonResponse
    {
        Gate::authorize('update', $attendanceDevice);

        $token = $this->issueToken->handle($attendanceDevice);

        $this->auditLog->log('token_regenerated', 'attendance_devices', $attendanceDevice);

        return $this->success([
            'device' => new AttendanceDeviceResource($attendanceDevice->load('organization')),
            'token' => $token,
        ], 'Yangi token yaratildi.');
    }
}
