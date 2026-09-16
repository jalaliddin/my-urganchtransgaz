<?php

namespace App\Enums;

enum KpiCalculationType: string
{
    case Manual = 'manual';
    case Percentage = 'percentage';
    case Quantity = 'quantity';
    case Rating = 'rating';
    case Formula = 'formula';
}
