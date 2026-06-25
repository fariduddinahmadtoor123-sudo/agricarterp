<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('module', 64);
            $table->string('action', 32);
            $table->string('label');
            $table->timestamps();

            $table->index(['module', 'action']);
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();

            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_number_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });

        Schema::create('user_application_number_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });

        $this->seedRolesAndPermissions();
    }

    protected function seedRolesAndPermissions(): void
    {
        $now = now();

        $superAdminId = DB::table('roles')->insertGetId([
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'description' => 'Full system access. This role cannot be deleted.',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionIds = [];

        foreach (config('users.permission_matrix', []) as $module => $actions) {
            foreach ($actions as $action => $label) {
                $permissionIds[] = DB::table('permissions')->insertGetId([
                    'key' => $module . '.' . $action,
                    'module' => $module,
                    'action' => $action,
                    'label' => $label,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permission')->insert([
                'role_id' => $superAdminId,
                'permission_id' => $permissionId,
            ]);
        }

        DB::table('user_number_sequences')->insert(['id' => 1, 'last_number' => 0]);
        DB::table('user_application_number_sequences')->insert(['id' => 1, 'last_number' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('user_application_number_sequences');
        Schema::dropIfExists('user_number_sequences');
    }
};
