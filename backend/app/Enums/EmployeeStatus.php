<?php

namespace App\Enums;

enum EmployeeStatus: string
{
    case Active = 'active';
    case Vacation = 'vacation';
    case BusinessTrip = 'business_trip';
    case SickLeave = 'sick_leave';
    case Inactive = 'inactive';
    case Terminated = 'terminated';
}
