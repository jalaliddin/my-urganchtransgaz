<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class BuildTimesheet
{
    /**
     * Builds one monthly "tabel" grid: one row per employee, one cell per
     * calendar day in the range, plus a per-employee totals summary.
     *
     * This is a PHP-side pivot, not a single aggregate SQL query like
     * `AttendanceController::report()` — unlike a flat sum/count, a grid
     * with one column per day doesn't have a fixed, statically-known
     * column list SQL can select in one pass, and the underlying record
     * count for one month is small enough (employees × ~30) that loading
     * it once and folding it in PHP is both simpler and no slower in
     * practice than a dynamic pivot query would be.
     *
     * @param  Collection<int, Employee>  $employees  already scoped/filtered by the caller
     * @param  int[]  $workingDays  ISO weekdays (1 = Monday .. 7 = Sunday) considered working days
     * @return array<string, mixed>
     */
    public function handle(Collection $employees, Carbon $from, Carbon $to, array $workingDays): array
    {
        // Calendar-day precision only: $to commonly arrives as
        // endOfMonth() (23:59:59.999999), which would otherwise make
        // diffInDays() below return a value like 30.99999998 instead of
        // the whole day count a grid needs.
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        $recordsByEmployee = AttendanceRecord::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $records) => $records->keyBy(fn (AttendanceRecord $record) => $record->date->day));

        $dayCount = (int) $from->diffInDays($to) + 1;

        $rows = $employees->map(function (Employee $employee) use ($recordsByEmployee, $from, $dayCount, $workingDays) {
            $records = $recordsByEmployee->get($employee->id, collect());
            $totals = $this->emptyTotals();
            $days = [];

            for ($day = 1; $day <= $dayCount; $day++) {
                $date = $from->copy()->addDays($day - 1);
                /** @var AttendanceRecord|null $record */
                $record = $records->get($day);
                $isWeekend = ! in_array($date->isoWeekday(), $workingDays, true);

                if ($record) {
                    $totals["{$record->status->value}_count"]++;
                    $totals['total_worked_minutes'] += $record->worked_minutes ?? 0;
                }

                $days[] = [
                    'day' => $day,
                    'status' => $record?->status->value,
                    'short_code' => $record?->status->shortCode(),
                    'worked_minutes' => $record?->worked_minutes,
                    'is_weekend' => $isWeekend,
                ];
            }

            return [
                'employee_id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->fullName(),
                'department' => $employee->department?->name,
                'days' => $days,
                'totals' => $totals,
            ];
        })->values()->all();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'day_count' => $dayCount,
            'working_days' => $workingDays,
            'employees' => $rows,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyTotals(): array
    {
        return [
            'present_count' => 0,
            'late_count' => 0,
            'early_leave_count' => 0,
            'absent_count' => 0,
            'business_trip_count' => 0,
            'vacation_count' => 0,
            'sick_leave_count' => 0,
            'total_worked_minutes' => 0,
        ];
    }
}
