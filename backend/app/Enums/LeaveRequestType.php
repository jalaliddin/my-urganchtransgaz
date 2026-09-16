<?php

namespace App\Enums;

enum LeaveRequestType: string
{
    case Vacation = 'vacation';
    case BusinessTrip = 'business_trip';
    case SickLeave = 'sick_leave';
    case Other = 'other';
}
