<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceSource;
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
     * Idempotent by design: a repeat check-in for a day that already has
     * one is a no-op here — duplicate sensor reads are normal for a
     * biometric device. The self-service controller additionally rejects
     * a repeat with a 409 so a person gets clear feedback; the device
     * webhook just lets this no-op through.
     */
    public function handle(Employee $employee, Carbon $at, AttendanceSource $source): AttendanceRecord
    {
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
