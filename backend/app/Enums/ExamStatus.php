<?php

namespace App\Enums;

enum ExamStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';
}
