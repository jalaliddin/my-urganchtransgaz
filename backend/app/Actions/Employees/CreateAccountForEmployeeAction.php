<?php

namespace App\Actions\Employees;

use App\Enums\AuthSource;
use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Opens a login for an employee who was created without one — the same
 * account-creation shape CreateEmployeeAction runs inline for a brand new
 * employee, here as its own step for an existing one.
 */
class CreateAccountForEmployeeAction
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Employee $employee, array $data): User
    {
        return DB::transaction(function () use ($employee, $data) {
            $user = User::create([
                'name' => $employee->fullName(),
                'username' => $data['username'],
                'email' => $data['corporate_email'] ?? $employee->email,
                'password' => Hash::make($data['password']),
                'status' => UserStatus::Active,
                'auth_source' => AuthSource::Local,
            ]);

            $user->assignRole($data['role']);

            $employee->update(['user_id' => $user->id]);

            $this->auditLog->log('account_created', 'employees', $employee, newValues: ['user_id' => $user->id]);

            return $user;
        });
    }
}
