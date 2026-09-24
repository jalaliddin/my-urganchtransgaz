<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceSource;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Support\Carbon;

class RecordCheckInAction
{
    public function __construct(
        private CalculateAttendanceStatus $calculateStatus,
        private CalculateWorkedMinutesFromEvents $calculateWorkedMinutes,
    ) {
        //
    }

    /**
     * Every call logs a raw AttendanceEvent — this is what lets "necha
     * bora kirib chiqqan" (how many times they entered/exited that day)
     * be shown, independent of the daily-summary behavior below, and lets
     * worked_minutes be the sum of every session that day (see
     * CalculateWorkedMinutesFromEvents) rather than just first-in to
     * last-out, which would wrongly count a lunch break as worked time.
     *
     * The AttendanceRecord daily summary's check_in itself stays
     * idempotent by design: a repeat check-in for a day that already has
     * one leaves the summary's check_in untouched — a person's first
     * arrival of the day is their check-in, no matter how many more times
     * they scan in after that. The self-service controller additionally
     * rejects a repeat with a 409 so a person gets clear feedback; the
     * device webhook just lets this no-op through. worked_minutes is
     * still recomputed either way, since an out-of-order device event
     * (a check-out delivered before this check-in, despite this one's
     * own timestamp being earlier) can otherwise leave it stale.
     */
    public function handle(Employee $employee, Carbon $at, AttendanceSource $source): AttendanceRecord
    {
        AttendanceEvent::create([
            'employee_id' => $employee->id,
            'type' => AttendanceEventType::CheckIn,
            'occurred_at' => $at,
            'source' => $source,
        ]);

        $record = AttendanceRecord::firstOrNew([
            'employee_id' => $employee->id,
            'date' => $at->toDateString(),
        ]);

        if (! $record->exists) {
            $record->source = $source;
        }

        if (! $record->check_in) {
            $record->check_in = $at;
        }

        $record->status = $this->calculateStatus->handle($record->check_in, $record->check_out);
        $record->worked_minutes = $this->calculateWorkedMinutes->handle($employee->id, $at->toDateString());
        $record->save();

        return $record;
    }
}
