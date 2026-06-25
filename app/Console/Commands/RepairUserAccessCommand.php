<?php

namespace App\Console\Commands;

use App\Services\Users\UserAccessRepairService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RepairUserAccessCommand extends Command
{
    protected $signature = 'users:repair-access {email=admin@agricarterp.com}';

    protected $description = 'Ensure RBAC tables exist and assign Super Admin role to a user';

    public function handle(): int
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('users', 'role_id')) {
            $this->error('RBAC migrations are missing. Run: php artisan migrate');

            return self::FAILURE;
        }

        app(UserAccessRepairService::class)->repair();

        $superAdminId = DB::table('roles')->where('slug', 'super_admin')->value('id');
        $email = (string) $this->argument('email');

        $updated = DB::table('users')
            ->where('email', $email)
            ->update([
                'role_id' => $superAdminId,
                'status' => 'active',
            ]);

        if ($updated === 0 && ! DB::table('users')->where('email', $email)->exists()) {
            $this->warn("No user found for {$email}.");

            return self::FAILURE;
        }

        $this->info("Super Admin access ensured for {$email}.");

        return self::SUCCESS;
    }
}
