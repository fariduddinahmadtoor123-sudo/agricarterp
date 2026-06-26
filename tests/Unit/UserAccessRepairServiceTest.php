<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Users\UserAccessRepairService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserAccessRepairServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_repairs_configured_email_without_role(): void
    {
        config(['users.restore_super_admin_emails' => ['farid@example.com']]);

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $superAdminId = DB::table('roles')->where('slug', 'super_admin')->value('id');

        User::factory()->create([
            'email' => 'farid@example.com',
            'role_id' => null,
            'status' => 'inactive',
        ]);

        app(UserAccessRepairService::class)->repairUsersWithoutRoles((int) $superAdminId);

        $user = User::query()->where('email', 'farid@example.com')->first();

        $this->assertSame((int) $superAdminId, $user->role_id);
        $this->assertSame(User::STATUS_ACTIVE, $user->status);
    }
}
