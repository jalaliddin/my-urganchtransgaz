<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * The monthly "tabel" as a flat spreadsheet: one row per employee, one
 * column per day (a short status code — see AttendanceStatus::shortCode)
 * plus per-employee totals. Built from the already-computed grid
 * (BuildTimesheet), not a live query — a grid this shape has no natural
 * Eloquent query to stream from the way a flat report does.
 */
class TimesheetExport implements FromArray, WithHeadings
{
    use Exportable;

    /**
     * @param  array<string, mixed>  $timesheet  BuildTimesheet's output
     */
    public function __construct(private array $timesheet)
    {
        //
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return collect($this->timesheet['employees'])->map(function (array $row) {
            $cells = collect($row['days'])->map(
                fn (array $day) => $day['short_code'] ?? ($day['is_weekend'] ? 'D' : '')
            )->all();

            return [
                $row['employee_number'],
                $row['full_name'],
                $row['department'],
                ...$cells,
                $row['totals']['present_count'],
                $row['totals']['late_count'],
                $row['totals']['early_leave_count'],
                $row['totals']['absent_count'],
                $row['totals']['business_trip_count'],
                $row['totals']['vacation_count'],
                $row['totals']['sick_leave_count'],
                $row['totals']['excused_count'],
                round($row['totals']['total_worked_minutes'] / 60, 1),
            ];
        })->all();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        $dayHeadings = range(1, $this->timesheet['day_count']);

        return [
            'Tabel raqami', 'F.I.Sh.', "Bo'lim",
            ...$dayHeadings,
            'Keldi', 'Kech keldi', 'Erta ketdi', 'Kelmadi', 'Xizmat safarida', "Ta'tilda", 'Bemor varaqasida', 'Sababli', 'Jami soat',
        ];
    }
}
