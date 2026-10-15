<?php

namespace App\Http\Controllers\Api\V1\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncAttendanceDeviceUsersRequest;
use App\Models\AttendanceDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AttendanceDeviceUserController extends Controller
{
    /**
     * Replaces the device's list of enrolled people with the one its
     * client just read from the terminal (the server can't reach a
     * terminal on the site's network itself). A partial list only adds
     * and updates; it never removes anyone.
     */
    public function sync(SyncAttendanceDeviceUsersRequest $request): JsonResponse
    {
        /** @var AttendanceDevice $device */
        $device = $request->attributes->get('attendanceDevice');

        if ($request->string('device_id')->toString() !== $device->device_id) {
            return $this->error('device_id does not match the authenticated device.', 422);
        }

        $users = collect($request->validated('users'));
        $complete = $request->boolean('complete');
        $now = now();

        DB::transaction(function () use ($device, $users, $complete, $now) {
            $users->chunk(500)->each(fn ($chunk) => $device->users()->upsert(
                $chunk->map(fn (array $user) => [
                    'attendance_device_id' => $device->id,
                    'user_id' => $user['user_id'],
                    'name' => $user['name'] ?? null,
                    'card_no' => $user['card_no'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
                ['attendance_device_id', 'user_id'],
                ['name', 'card_no', 'updated_at'],
            ));

            if ($complete) {
                $device->users()->whereNotIn('user_id', $users->pluck('user_id'))->delete();
            }

            $device->update(['roster_synced_at' => $now, 'roster_complete' => $complete]);
        });

        return $this->success(['count' => $device->users()->count()], 'Ro\'yxat yangilandi.');
    }
}
