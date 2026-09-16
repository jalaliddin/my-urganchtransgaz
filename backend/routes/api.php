<?php

use App\Http\Controllers\Api\V1\Integrations\AttendanceEventController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    require base_path('routes/api/auth.php');

    Route::middleware('auth:sanctum')->group(function () {
        require base_path('routes/api/organizations.php');
        require base_path('routes/api/departments.php');
        require base_path('routes/api/employees.php');
        require base_path('routes/api/profile.php');
        require base_path('routes/api/documents.php');
        require base_path('routes/api/change-requests.php');
        require base_path('routes/api/notifications.php');
        require base_path('routes/api/attendance.php');
        require base_path('routes/api/tasks.php');
        require base_path('routes/api/exams.php');
        require base_path('routes/api/kpi.php');
        require base_path('routes/api/announcements.php');
    });

    // Biometric/integration device webhook — its own token scheme
    // (AuthenticateAttendanceDevice), never Sanctum user auth.
    Route::middleware('attendance.device')->group(function () {
        Route::post('integrations/attendance/events', [AttendanceEventController::class, 'store'])
            ->name('integrations.attendance.events');
    });
});
