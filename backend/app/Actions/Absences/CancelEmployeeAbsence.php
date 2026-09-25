<?php

namespace App\Actions\Absences;

use App\Models\EmployeeAbsence;
use App\Models\User;
use App\Notifications\AbsenceCancelled;
use Illuminate\Support\Facades\DB;

class CancelEmployeeAbsence
{
    public function __construct(
        private ApplyAbsenceToAttendance $attendance,
        private SyncEmployeeAbsenceStatus $status,
    ) {
        //
    }

    /**
     * Cancelling keeps the record (and its order/certificate file) for the
     * history, but it stops excusing any day and stops counting toward
     * the employee's status or leave balance.
     */
    public function handle(EmployeeAbsence $absence, User $user, ?string $reason): EmployeeAbsence
    {
        DB::transaction(function () use ($absence, $user, $reason) {
            $absence->update([
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason,
            ]);

            $this->attendance->revert($absence);
            $this->status->handle($absence->employee, today());
        });

        $absence->employee->user?->notify(new AbsenceCancelled($absence));

        return $absence;
    }
}
