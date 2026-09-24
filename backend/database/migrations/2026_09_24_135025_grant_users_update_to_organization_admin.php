<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Mirrors the organization-admin grant in RolePermissionSeeder, which
     * fresh installs get instead; this reaches installs that are already
     * seeded, since a redeploy only runs migrations. Scoping to the admin's
     * own organization is enforced in the Form Requests, not here.
     */
    public function up(): void
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

    /**
     * Revokes only this grant — the permission itself stays, since hr and
     * central-admin rely on it.
     */
    public function down(): void
    {
        DB::table('role_has_permissions')
            ->where('permission_id', $this->permissionId())
            ->where('role_id', $this->roleId())
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionId(): ?int
    {
        return DB::table('permissions')->where('name', 'users.update')->where('guard_name', 'web')->value('id');
    }

    private function roleId(): ?int
    {
        return DB::table('roles')->where('name', 'organization-admin')->where('guard_name', 'web')->value('id');
    }
};
