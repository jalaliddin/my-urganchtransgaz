<?php

namespace App\Actions\Employees;

use App\Enums\AuthSource;
use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateEmployeeAction
{
    public function __construct(private AuditLogService $auditLog)
    {
        //
    }

    /**
     * Create an employee profile and, optionally, the linked user account
     * with an assigned role.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            $userId = null;

            if ($data['create_account'] ?? false) {
                $user = User::create([
                    'name' => trim("{$data['first_name']} {$data['last_name']}"),
                    'username' => $data['username'],
                    'email' => $data['corporate_email'] ?? ($data['email'] ?? null),
                    'password' => Hash::make($data['password']),
                    'status' => UserStatus::Active,
                    'auth_source' => AuthSource::Local,
                ]);

                $user->assignRole($data['role']);

                $userId = $user->id;
            }

            $employee = Employee::create([
                ...Arr::except($data, ['create_account', 'username', 'password', 'role']),
                'user_id' => $userId,
            ])->refresh();

            $this->auditLog->log('created', 'employees', $employee, newValues: $employee->toArray());

            return $employee;
        });
    }
}
