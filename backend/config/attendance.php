<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Working hours
    |--------------------------------------------------------------------------
    |
    | The standard work day, used to compute a check-in/check-out's
    | attendance status. Grace periods absorb minor clock drift or a
    | reasonable buffer before a check-in/check-out counts as late/early.
    |
    */

    'work_start' => env('ATTENDANCE_WORK_START', '09:00'),

    'work_end' => env('ATTENDANCE_WORK_END', '18:00'),

    'late_grace_minutes' => env('ATTENDANCE_LATE_GRACE_MINUTES', 15),

    'early_leave_grace_minutes' => env('ATTENDANCE_EARLY_LEAVE_GRACE_MINUTES', 15),

];
