<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceEventType;
use App\Models\AttendanceEvent;
use Illuminate\Support\Carbon;

/**
 * Sums every check-in/check-out session in a day's raw scan log, instead
 * of just (last check-out minus first check-in) — a person who steps out
 * for lunch and back in again must not have that gap counted as worked
 * time. Events are paired in chronological order: a check-in opens a
 * session, the next check-out closes it and adds its duration. A
 * check-in while a session is already open, or a check-out with none
 * open, is a duplicate/out-of-order scan and is ignored rather than
 * corrupting the running total. A session left open at the end of the
 * scanned range (no matching check-out yet) contributes nothing until
 * it closes.
 */
class CalculateWorkedMinutesFromEvents
{
    public function handle(int $employeeId, string $date): int
    {
        $events = AttendanceEvent::where('employee_id', $employeeId)
            ->whereDate('occurred_at', $date)
            ->orderBy('occurred_at')
            ->get();

        $totalMinutes = 0;
        $sessionStart = null;

        foreach ($events as $event) {
            if ($event->type === AttendanceEventType::CheckIn) {
                $sessionStart ??= $event->occurred_at;

                continue;
            }

            if ($sessionStart instanceof Carbon) {
                $totalMinutes += $sessionStart->diffInMinutes($event->occurred_at);
                $sessionStart = null;
            }
        }

        return $totalMinutes;
    }
}
