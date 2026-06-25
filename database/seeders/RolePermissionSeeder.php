<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $superAdminId = $this->ensureSuperAdminRole();
        $this->syncPermissions($superAdminId);
        $this->ensureSequences();
        $this->repairSuperAdminUsers($superAdminId);
    }

    protected function ensureSuperAdminRole(): int
    {
        $existingId = DB::table('roles')->where('slug', 'super_admin')->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        $now = now();

        return (int) DB::table('roles')->insertGetId([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'description' => 'Full system access. This role cannot be deleted.',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    protected function syncPermissions(int $superAdminId): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $permissionIds = [];

        foreach (config('users.permission_matrix', []) as $module => $actions) {
            foreach ($actions as $action => $label) {
                $key = $module . '.' . $action;

                $permissionId = DB::table('permissions')->where('key', $key)->value('id');

                if (! $permissionId) {
                    $permissionId = DB::table('permissions')->insertGetId([
                        'key' => $key,
                        'module' => $module,
                        'action' => $action,
                        'label' => $label,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $permissionIds[] = $permissionId;
            }
        }

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permission')->updateOrInsert(
                [
                    'role_id' => $superAdminId,
                    'permission_id' => $permissionId,
                ],
                [],
            );
        }
    }

    protected function ensureSequences(): void
    {
        if (Schema::hasTable('user_number_sequences')) {
            DB::table('user_number_sequences')->updateOrInsert(['id' => 1], ['last_number' => 0]);
        }

        if (Schema::hasTable('user_application_number_sequences')) {
            DB::table('user_application_number_sequences')->updateOrInsert(['id' => 1], ['last_number' => 0]);
        }
    }

    protected function repairSuperAdminUsers(int $superAdminId): void
    {
        if (! Schema::hasColumn('users', 'role_id')) {
            return;
        }

        DB::table('users')
            ->whereNull('role_id')
            ->where('email', 'admin@agricarterp.com')
            ->update([
                'role_id' => $superAdminId,
                'status' => 'active',
            ]);
    }
}
