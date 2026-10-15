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
        'central-admin' => ['attendance_devices.manage'],
        'hr' => ['attendance_devices.manage'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore([
            'name' => 'attendance_devices.manage',
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

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
        DB::table('permissions')->where('name', 'attendance_devices.manage')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
