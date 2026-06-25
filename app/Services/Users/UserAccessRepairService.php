<?php

namespace App\Services\Users;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserAccessRepairService
{
    public function repair(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('users', 'role_id')) {
            return;
        }

        app(RolePermissionSeeder::class)->run();
    }

    public function repairUsersWithoutRoles(int $superAdminRoleId): void
    {
        $configuredEmails = array_filter(config('users.restore_super_admin_emails', [
            'admin@agricarterp.com',
        ]));

        foreach ($configuredEmails as $email) {
            DB::table('users')
                ->where('email', $email)
                ->whereNull('role_id')
                ->update([
                    'role_id' => $superAdminRoleId,
                    'status' => 'active',
                ]);
        }

        if (DB::table('users')->whereNotNull('role_id')->count() > 0) {
            return;
        }

        $userCount = DB::table('users')->count();

        if ($userCount === 0) {
            return;
        }

        DB::table('users')
            ->whereNull('role_id')
            ->update([
                'role_id' => $superAdminRoleId,
                'status' => 'active',
            ]);
    }
}
