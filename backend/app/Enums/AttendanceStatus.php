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
}
