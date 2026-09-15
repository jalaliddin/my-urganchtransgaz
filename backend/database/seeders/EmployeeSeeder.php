<?php

namespace Database\Seeders;

use App\Enums\AuthSource;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds one demo login per role (all sharing the password "password", so QA
 * can exercise every permission level) plus a handful of profile-only
 * employees for realistic directory/listing data. All of this is clearly
 * demo data for local development, not production seed content.
 */
class EmployeeSeeder extends Seeder
{
    /**
     * Role => [organization code, department code or null, position title, username].
     *
     * @var array<string, array{0: string, 1: ?string, 2: string, 3: string}>
     */
    private array $demoUsers = [
        'super-admin' => ['UTG-000', null, 'Direktor', 'superadmin'],
        'central-admin' => ['UTG-000', 'HR', 'Direktor o\'rinbosari', 'centraladmin'],
        'hr' => ['UTG-000', 'HR', 'Bosh mutaxassis', 'hr'],
        'safety-manager' => ['UTG-000', 'MMSX', 'Bosh mutaxassis', 'safety'],
        'technical-policy' => ['UTG-000', 'TPS', 'Bosh mutaxassis', 'techpolicy'],
        'organization-admin' => ['UTG-001', 'ADM', 'Direktor', 'orgadmin'],
        'department-manager' => ['UTG-001', 'TEX', 'Bo\'lim boshlig\'i', 'deptmanager'],
        'manager' => ['UTG-002', 'TEX', 'Bo\'lim boshlig\'i', 'manager'],
        'employee' => ['UTG-001', 'TEX', 'Operator', 'employee'],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('password');
        $departmentManagerEmployee = null;

        foreach ($this->demoUsers as $role => [$orgCode, $deptCode, $positionTitle, $username]) {
            $organization = Organization::where('code', $orgCode)->firstOrFail();
            $department = $deptCode ? Department::where('organization_id', $organization->id)->where('code', $deptCode)->first() : null;
            $position = Position::where('organization_id', $organization->id)->where('title', $positionTitle)->first();

            $user = User::create([
                'name' => ucfirst(str_replace('-', ' ', $role)),
                'username' => $username,
                'email' => "{$username}@urtg.uz",
                'email_verified_at' => now(),
                'password' => $password,
                'status' => UserStatus::Active,
                'auth_source' => AuthSource::Local,
            ]);

            $user->assignRole($role);

            $employee = Employee::factory()->create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'department_id' => $department?->id,
                'position_id' => $position?->id,
                'employee_number' => 'EMP'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'corporate_email' => "{$username}@urtg.uz",
            ]);

            if ($role === 'department-manager') {
                $departmentManagerEmployee = $employee;
            }
        }

        if ($departmentManagerEmployee) {
            Department::where('organization_id', $departmentManagerEmployee->organization_id)
                ->where('id', $departmentManagerEmployee->department_id)
                ->update(['manager_id' => $departmentManagerEmployee->id]);
        }

        Organization::query()->each(function (Organization $organization) {
            $departmentIds = Department::where('organization_id', $organization->id)->pluck('id');
            $positionIds = Position::where('organization_id', $organization->id)->pluck('id');

            for ($i = 0; $i < 4; $i++) {
                Employee::factory()->create([
                    'organization_id' => $organization->id,
                    'department_id' => $departmentIds->random(),
                    'position_id' => $positionIds->random(),
                ]);
            }
        });
    }
}
