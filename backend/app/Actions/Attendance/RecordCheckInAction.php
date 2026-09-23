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
    public function __construct(private CalculateAttendanceStatus $calculateStatus)
    {
        //
    }

    /**
     * Every call logs a raw AttendanceEvent — this is what lets "necha
     * bora kirib chiqqan" (how many times they entered/exited that day)
     * be shown, independent of the daily-summary behavior below.
     *
     * The AttendanceRecord daily summary itself stays idempotent by
     * design: a repeat check-in for a day that already has one leaves the
     * summary's check_in untouched — a person's first arrival of the day
     * is their check-in, no matter how many more times they scan in
     * after that. The self-service controller additionally rejects a
     * repeat with a 409 so a person gets clear feedback; the device
     * webhook just lets this no-op through.
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

        if ($record->exists && $record->check_in) {
            return $record;
        }

        $record->check_in = $at;
        $record->source = $source;
        $record->status = $this->calculateStatus->handle($at, $record->check_out);
        $record->save();

        return $record;
    }
}
