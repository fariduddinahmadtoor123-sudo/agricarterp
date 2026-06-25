<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\RolesPermissions;
use App\Filament\Pages\Settings\Users;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsersSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_settings_page_loads_for_super_admin(): void
    {
        $user = User::factory()->superAdmin()->create([
            'user_number' => 'USR-000001',
        ]);

        $this->actingAs($user)
            ->get('/admin/settings/users')
            ->assertOk()
            ->assertSee('Users');

        Livewire::actingAs($user)
            ->test(Users::class)
            ->assertSee('Add User');
    }

    public function test_roles_permissions_page_loads_for_super_admin(): void
    {
        $user = User::factory()->superAdmin()->create([
            'user_number' => 'USR-000001',
        ]);

        $this->actingAs($user)
            ->get('/admin/settings/roles-permissions')
            ->assertOk()
            ->assertSee('Permission');

        Livewire::actingAs($user)
            ->test(RolesPermissions::class)
            ->assertSee('Super Admin');
    }
}
