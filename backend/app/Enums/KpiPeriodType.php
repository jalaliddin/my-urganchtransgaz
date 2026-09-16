<?php

namespace App\Enums;

enum KpiPeriodType: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Annual = 'annual';
}
