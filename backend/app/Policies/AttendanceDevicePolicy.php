<?php

namespace App\Policies;

use App\Models\AttendanceDevice;
use App\Models\User;

/**
 * A device token records attendance for any employee in any organization
 * (the webhook finds people by their Dahua ID company-wide), so managing
 * devices is its own company-wide permission rather than attendance.manage,
 * which organization admins also hold.
 */
class AttendanceDevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance_devices.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('attendance_devices.manage');
    }

    public function update(User $user, AttendanceDevice $attendanceDevice): bool
    {
        return $user->can('attendance_devices.manage');
    }
}
