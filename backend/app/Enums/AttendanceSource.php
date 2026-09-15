<?php

namespace App\Enums;

enum AttendanceSource: string
{
    case Biometric = 'biometric';
    case Manual = 'manual';
    case Mobile = 'mobile';
    case Web = 'web';
    case Api = 'api';
    case System = 'system';
}
