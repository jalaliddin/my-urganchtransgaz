<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceSource;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Support\Carbon;

class RecordCheckOutAction
{
    public function __construct(private CalculateAttendanceStatus $calculateStatus)
    {
        //
    }

    /**
     * Idempotent, same reasoning as RecordCheckInAction. If a check-out
     * arrives with no prior check-in row for the day (an out-of-order
     * device event), a record is still created so the check-out isn't
     * lost — just without worked_minutes, since there is nothing to
     * measure from.
     */
    public function handle(Employee $employee, Carbon $at, AttendanceSource $source): AttendanceRecord
    {
        $record = AttendanceRecord::firstOrNew([
            'employee_id' => $employee->id,
            'date' => $at->toDateString(),
        ]);

        if ($record->exists && $record->check_out) {
            return $record;
        }

        if (! $record->exists) {
            $record->source = $source;
        }

        $record->check_out = $at;
        $record->status = $this->calculateStatus->handle($record->check_in, $at);

        if ($record->check_in) {
            $record->worked_minutes = (int) abs($record->check_in->diffInMinutes($at));
        }

        $record->save();

        return $record;
    }
}
