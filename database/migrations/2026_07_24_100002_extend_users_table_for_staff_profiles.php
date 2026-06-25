<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_number', 12)->nullable()->unique()->after('id');
            $table->foreignId('role_id')->nullable()->after('email')->constrained('roles')->nullOnDelete();
            $table->string('status', 20)->default('active')->after('role_id');
            $table->text('full_address')->nullable()->after('name');
            $table->index('status');
        });

        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');

        if ($superAdminRoleId) {
            foreach (DB::table('users')->orderBy('id')->get() as $user) {
                $roleId = ($user->role ?? 'staff') === 'super_admin'
                    ? $superAdminRoleId
                    : null;

                DB::table('users')->where('id', $user->id)->update([
                    'role_id' => $roleId,
                    'status' => 'active',
                ]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('staff')->after('email');
            $table->index('role');
        });

        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $slug = DB::table('roles')->where('id', $user->role_id)->value('slug');

            DB::table('users')->where('id', $user->id)->update([
                'role' => $slug === 'super_admin' ? 'super_admin' : 'staff',
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropColumn(['user_number', 'status', 'full_address']);
        });
    }
};
