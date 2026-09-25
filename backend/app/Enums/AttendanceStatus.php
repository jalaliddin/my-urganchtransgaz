<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case EarlyLeave = 'early_leave';
    case Absent = 'absent';
    case BusinessTrip = 'business_trip';
    case Vacation = 'vacation';
    case SickLeave = 'sick_leave';
    case Excused = 'excused';

    /**
     * The single-letter/short code a printed monthly tabel (timesheet)
     * cell shows for this status — the standard abbreviated form HR staff
     * in this region already expect from a paper tabel, not something
     * invented for this app.
     */
    public function shortCode(): string
    {
        return match ($this) {
            self::Present => 'K',
            self::Late => 'K/K',
            self::EarlyLeave => 'K/E',
            self::Absent => 'N',
            self::BusinessTrip => 'X',
            self::Vacation => 'T',
            self::SickLeave => 'B',
            self::Excused => 'S',
        };
    }
}
