<?php

namespace App\Enums;

enum LoginStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
}
