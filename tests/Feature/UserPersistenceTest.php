<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserApplication;
use App\Services\Users\UserApplicationPersistenceService;
use App\Services\Users\UserPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_staff_user_with_profile_data(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $roleId = Role::query()->create([
            'name' => 'Sales Staff',
            'slug' => 'sales_staff',
            'description' => 'Sales team',
            'is_system' => false,
        ])->id;

        $this->actingAs($admin);

        $user = app(UserPersistenceService::class)->create([
            'name' => 'Ali Khan',
            'email' => 'ali@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => $roleId,
            'status' => User::STATUS_ACTIVE,
            'full_address' => 'Lahore',
            'phones' => [
                ['contact_person' => 'Ali', 'phone_number' => '03001234567'],
            ],
            'bank_accounts' => [
                [
                    'bank_name' => 'HBL',
                    'branch_name' => 'Main',
                    'account_title' => 'Ali Khan',
                    'iban_account_number' => 'PK00HABB0000001123456702',
                ],
            ],
            'documents' => [],
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'ali@example.com',
            'user_number' => 'USR-000001',
        ]);

        $this->assertDatabaseHas('user_phones', [
            'user_id' => $user->id,
            'phone_number' => '03001234567',
        ]);
    }

    public function test_public_application_is_stored_as_pending(): void
    {
        app(UserApplicationPersistenceService::class)->submit([
            'name' => 'Sara Ahmed',
            'email' => 'sara@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'full_address' => 'Karachi',
            'phones' => [
                ['contact_person' => 'Sara', 'phone_number' => '03007654321'],
            ],
            'bank_accounts' => [
                [
                    'bank_name' => 'UBL',
                    'branch_name' => 'Clifton',
                    'account_title' => 'Sara Ahmed',
                    'iban_account_number' => 'PK00UNIL0000001123456702',
                ],
            ],
            'documents' => [],
        ]);

        $this->assertDatabaseHas('user_applications', [
            'email' => 'sara@example.com',
            'status' => UserApplication::STATUS_PENDING,
            'application_number' => 'APP-000001',
        ]);
    }
}
