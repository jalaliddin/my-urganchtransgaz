<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Support\Str;

class LoginIdentifierResolver
{
    /**
     * Resolve a user from a login identifier that may be an email address,
     * a username, or an employee number.
     */
    public function resolve(string $identifier): ?User
    {
        if (Str::contains($identifier, '@')) {
            return User::where('email', $identifier)->first();
        }

        return User::where('username', $identifier)
            ->orWhereHas('employee', fn ($query) => $query->where('employee_number', $identifier))
            ->first();
    }
}
