<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * The full granular permission contract for the portal. Not every
     * permission is enforced yet (documents/attendance/tasks/exams/kpi/
     * announcements arrive in later phases), but the roles and their grants
     * are seeded now so later phases only need to wire up authorization.
     *
     * @var array<string, string[]>
     */
    private array $permissions = [
        'users' => ['view', 'create', 'update', 'delete'],
        'organizations' => ['view', 'create', 'update', 'delete'],
        'departments' => ['view', 'create', 'update', 'delete'],
        'positions' => ['view', 'create', 'update', 'delete'],
        'employees' => ['view', 'create', 'update', 'delete'],
        'documents' => ['view', 'upload', 'approve', 'delete'],
        'attendance' => ['view', 'manage'],
        'tasks' => ['view', 'create', 'update', 'complete', 'assign'],
        'exams' => ['view', 'create', 'manage', 'evaluate'],
        'kpi' => ['view', 'manage'],
        'announcements' => ['view', 'create', 'publish'],
        'issues' => ['create', 'view', 'resolve'],
        'issue_categories' => ['manage'],
        'audit_logs' => ['view'],
        'settings' => ['manage'],
    ];

    /**
     * Permissions granted to each role beyond the default "employee" grants
     * every authenticated user receives. Super-admin bypasses this list
     * entirely via a Gate::before check, so it is deliberately omitted.
     *
     * @var array<string, string[]>
     */
    private array $rolePermissions = [
        'central-admin' => ['*'],
        'organization-admin' => [
            'organizations.view',
            'departments.view', 'departments.create', 'departments.update',
            'positions.view', 'positions.create', 'positions.update',
            'employees.view', 'employees.create', 'employees.update',
            'users.view',
            'documents.view', 'documents.approve',
            'attendance.view', 'attendance.manage',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign',
            'kpi.view', 'kpi.manage',
            'announcements.view', 'announcements.create',
            'issues.create', 'issues.view',
        ],
        'department-manager' => [
            'organizations.view', 'departments.view',
            'positions.view',
            'employees.view',
            'attendance.view',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign',
            'kpi.view',
            'announcements.view',
            'issues.create', 'issues.view',
        ],
        'hr' => [
            'organizations.view', 'departments.view',
            'positions.view', 'positions.create', 'positions.update',
            'employees.view', 'employees.create', 'employees.update',
            'users.view', 'users.create', 'users.update',
            'documents.view', 'documents.upload', 'documents.approve', 'documents.delete',
            'attendance.view', 'attendance.manage',
            'announcements.view', 'announcements.create',
            'issues.create', 'issues.view',
        ],
        'safety-manager' => [
            'organizations.view', 'departments.view', 'positions.view', 'employees.view',
            'exams.view', 'exams.create', 'exams.manage', 'exams.evaluate',
            'announcements.view',
            'issues.create', 'issues.view',
        ],
        'technical-policy' => [
            'organizations.view', 'departments.view', 'positions.view', 'employees.view',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign',
            'announcements.view',
            'issues.create', 'issues.view', 'issues.resolve',
            'issue_categories.manage',
        ],
        'manager' => [
            'departments.view', 'positions.view', 'employees.view',
            'attendance.view',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.assign',
            'kpi.view',
            'announcements.view',
            'issues.create', 'issues.view',
        ],
        'employee' => [
            'documents.view', 'documents.upload',
            'attendance.view',
            'tasks.view', 'tasks.update', 'tasks.complete',
            'exams.view',
            'kpi.view',
            'announcements.view',
            'issues.create', 'issues.view',
        ],
    ];

    /**
     * Seed the roles and permissions.
     */
    public function run(): void
    {
        DB::transaction(function () {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $permissionNames = [];

            foreach ($this->permissions as $module => $actions) {
                foreach ($actions as $action) {
                    $permissionNames[] = "{$module}.{$action}";
                }
            }

            foreach ($permissionNames as $name) {
                Permission::firstOrCreate(['name' => $name]);
            }

            $roleNames = [
                'super-admin', 'central-admin', 'organization-admin',
                'department-manager', 'hr', 'safety-manager',
                'technical-policy', 'manager', 'employee',
            ];

            foreach ($roleNames as $roleName) {
                $role = Role::firstOrCreate(['name' => $roleName]);

                $grants = $this->rolePermissions[$roleName] ?? [];

                if ($grants === ['*']) {
                    $role->syncPermissions($permissionNames);
                } elseif ($grants !== []) {
                    $role->syncPermissions($grants);
                }
            }
        });
    }
}
