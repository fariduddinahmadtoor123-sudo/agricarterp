<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->string('type', 32);
            $table->decimal('rate_value', 16, 4);
            $table->json('apply_on');
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('type');
            $table->unique('code');
        });

        $this->syncTaxSystemPermissions();
    }

    protected function syncTaxSystemPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $actions = config('users.permission_matrix.tax-system', []);

        if ($actions === []) {
            return;
        }

        $superAdminRoleId = DB::table('roles')->where('slug', 'super_admin')->value('id');

        foreach ($actions as $action => $label) {
            $key = 'tax-system.' . $action;

            $permissionId = DB::table('permissions')->where('key', $key)->value('id');

            if (! $permissionId) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'key' => $key,
                    'module' => 'tax-system',
                    'action' => $action,
                    'label' => $label,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($superAdminRoleId) {
                DB::table('role_permission')->updateOrInsert(
                    [
                        'role_id' => $superAdminRoleId,
                        'permission_id' => $permissionId,
                    ],
                    [],
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};
