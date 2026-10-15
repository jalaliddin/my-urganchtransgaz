<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceDevice;
use Illuminate\Support\Str;

class IssueAttendanceDeviceToken
{
    /**
     * Gives the device a fresh token and returns it in plain text — the
     * only time it exists in that form: only its SHA-256 hash is stored
     * (AuthenticateAttendanceDevice looks it up by hash), so a new one
     * immediately locks out whatever held the old one. Saves the device,
     * so a new one is created together with its first token.
     */
    public function handle(AttendanceDevice $device): string
    {
        $plainTextToken = Str::random(40);

        $device->forceFill(['token' => hash('sha256', $plainTextToken)])->save();

        return $plainTextToken;
    }
}
