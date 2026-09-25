<?php

namespace App\Console\Commands;

use App\Actions\Absences\SyncEmployeeAbsenceStatus;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('absences:sync-statuses')]
#[Description('Set each employee\'s status from the absence covering today: on vacation/business trip/sick leave when one starts, back to active when it ends.')]
class SyncAbsenceStatuses extends Command
{
    /**
     * Runs just after midnight, so an absence starting today shows on
     * the employee's status before the workday begins.
     */
    public function handle(SyncEmployeeAbsenceStatus $sync): int
    {
        $today = Carbon::today();

        Employee::query()
            ->whereIn('status', [
                EmployeeStatus::Active,
                EmployeeStatus::Vacation,
                EmployeeStatus::BusinessTrip,
                EmployeeStatus::SickLeave,
            ])
            ->whereHas('absences')
            ->chunkById(200, function ($employees) use ($sync, $today) {
                foreach ($employees as $employee) {
                    $sync->handle($employee, $today);
                }
            });

        return self::SUCCESS;
    }
}
