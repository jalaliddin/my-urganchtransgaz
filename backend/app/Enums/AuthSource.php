<?php

namespace App\Enums;

enum AuthSource: string
{
    case Local = 'local';
    case Ldap = 'ldap';
    case ActiveDirectory = 'active_directory';
    case Sso = 'sso';
}
