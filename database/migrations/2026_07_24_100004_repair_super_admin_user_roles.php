<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('users', 'role_id')) {
            return;
        }

        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');

        if (! $superAdminRoleId) {
            return;
        }

        DB::table('users')
            ->whereNull('role_id')
            ->where('email', 'admin@agricarterp.com')
            ->update([
                'role_id' => $superAdminRoleId,
                'status' => 'active',
            ]);

        if (DB::table('users')->whereNotNull('role_id')->count() === 0
            && DB::table('users')->count() === 1) {
            DB::table('users')
                ->whereNull('role_id')
                ->update([
                    'role_id' => $superAdminRoleId,
                    'status' => 'active',
                ]);
        }
    }

    public function down(): void
    {
        //
    }
};
