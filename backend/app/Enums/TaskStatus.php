<?php

namespace App\Enums;

enum TaskStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Overdue = 'overdue';
}
