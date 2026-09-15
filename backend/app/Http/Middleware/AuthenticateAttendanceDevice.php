<?php

namespace App\Http\Middleware;

use App\Models\AttendanceDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAttendanceDevice
{
    /**
     * Authenticate a biometric/integration device by its Bearer token —
     * a separate scheme from Sanctum's user tokens, since a device isn't a
     * User. The token is looked up by its SHA-256 hash (never stored in
     * plain text) and the resolved device is attached to the request for
     * the controller to read.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            abort(401, 'Device token is missing.');
        }

        $device = AttendanceDevice::where('token', hash('sha256', $token))
            ->where('is_active', true)
            ->first();

        if (! $device) {
            abort(401, 'Invalid or inactive device token.');
        }

        $device->update(['last_used_at' => now()]);

        $request->attributes->set('attendanceDevice', $device);

        return $next($request);
    }
}
