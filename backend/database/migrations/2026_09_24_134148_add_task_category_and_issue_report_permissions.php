<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Role => permissions granted to it. Mirrors the grants in
     * RolePermissionSeeder, which fresh installs get instead; this reaches
     * installs that are already seeded, since a redeploy only runs
     * migrations.
     *
     * @var array<string, string[]>
     */
    private array $grants = [
        'central-admin' => ['task_categories.manage', 'issues.report'],
        'technical-policy' => ['task_categories.manage', 'issues.report'],
        'organization-admin' => ['issues.report'],
        'department-manager' => ['issues.report'],
    ];

    /**
     * @var string[]
     */
    private array $permissions = ['task_categories.manage', 'issues.report'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        foreach ($this->permissions as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionIds = DB::table('permissions')->where('guard_name', 'web')->pluck('id', 'name');
        $roleIds = DB::table('roles')->where('guard_name', 'web')->pluck('id', 'name');

        foreach ($this->grants as $role => $permissions) {
            if (! $roleIds->has($role)) {
                continue;
            }

            foreach ($permissions as $permission) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionIds[$permission],
                    'role_id' => $roleIds[$role],
                ]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('permissions')->whereIn('name', $this->permissions)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
