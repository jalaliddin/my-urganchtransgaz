<?php

namespace App\Actions\Attendance;

use App\Enums\AttendanceEventType;
use App\Enums\AttendanceSource;
use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Support\Carbon;

class RecordCheckOutAction
{
    public function __construct(
        private CalculateAttendanceStatus $calculateStatus,
        private CalculateWorkedMinutesFromEvents $calculateWorkedMinutes,
    ) {
        //
    }

    /**
     * Every call logs a raw AttendanceEvent, same as RecordCheckInAction.
     *
     * Unlike check-in, the daily summary's check_out always advances to
     * the latest scan of the day (never regresses to an earlier one) —
     * a person can step out and back in (lunch, a supply run) without
     * their final exit of the day being lost behind an earlier one. If a
     * check-out arrives with no prior check-in row for the day (an
     * out-of-order device event), a record is still created so the
     * check-out isn't lost.
     *
     * worked_minutes is the sum of every check-in/check-out session that
     * day (see CalculateWorkedMinutesFromEvents), not just (this
     * check-out minus the day's first check-in) — that difference would
     * wrongly include a lunch break or any other gap between sessions as
     * worked time.
     */
    public function handle(Employee $employee, Carbon $at, AttendanceSource $source): AttendanceRecord
    {
        AttendanceEvent::create([
            'employee_id' => $employee->id,
            'type' => AttendanceEventType::CheckOut,
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

        if (! $record->check_out || $at->greaterThan($record->check_out)) {
            $record->check_out = $at;
        }

        $record->status = $this->calculateStatus->handle($record->check_in, $record->check_out);
        $record->worked_minutes = $this->calculateWorkedMinutes->handle($employee->id, $at->toDateString());
        $record->save();

        return $record;
    }
}
