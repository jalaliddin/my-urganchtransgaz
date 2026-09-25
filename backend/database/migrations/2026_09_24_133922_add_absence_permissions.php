<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Role => absence permissions granted to it. Mirrors the grants in
     * RolePermissionSeeder, which fresh installs get instead; this reaches
     * installs that are already seeded, since a redeploy only runs
     * migrations.
     *
     * @var array<string, string[]>
     */
    private array $grants = [
        'central-admin' => ['absences.view', 'absences.manage'],
        'hr' => ['absences.view', 'absences.manage'],
        'organization-admin' => ['absences.view', 'absences.manage'],
        'department-manager' => ['absences.view'],
        'manager' => ['absences.view'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        foreach (['absences.view', 'absences.manage'] as $name) {
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
        DB::table('permissions')->whereIn('name', ['absences.view', 'absences.manage'])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
