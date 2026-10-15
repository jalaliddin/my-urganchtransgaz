<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * attendance.view now means "see other employees' attendance in my
     * scope"; everyone always sees their own without it. A plain employee
     * holding it saw their whole organization's attendance. Mirrors the
     * employee grants in RolePermissionSeeder, which fresh installs get
     * instead; this reaches installs that are already seeded.
     */
    public function up(): void
    {
        DB::table('role_has_permissions')
            ->where('permission_id', $this->permissionId())
            ->where('role_id', $this->roleId())
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionId = $this->permissionId();
        $roleId = $this->roleId();

        if ($permissionId === null || $roleId === null) {
            return;
        }

        DB::table('role_has_permissions')->insertOrIgnore([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionId(): ?int
    {
        return DB::table('permissions')->where('name', 'attendance.view')->where('guard_name', 'web')->value('id');
    }

    private function roleId(): ?int
    {
        return DB::table('roles')->where('name', 'employee')->where('guard_name', 'web')->value('id');
    }
};
