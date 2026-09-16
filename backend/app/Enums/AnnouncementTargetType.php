<?php

namespace App\Enums;

enum AnnouncementTargetType: string
{
    case Everyone = 'everyone';
    case Central = 'central';
    case Organization = 'organization';
    case Department = 'department';
    case Employee = 'employee';
    case Role = 'role';
}
