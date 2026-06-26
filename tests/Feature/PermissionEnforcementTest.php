<?php

namespace Tests\Feature;

use App\Filament\Pages\Contacts\Customers;
use App\Filament\Pages\Settings\RolesPermissions;
use App\Filament\Pages\Settings\Users;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_users_page_requires_users_view_permission(): void
    {
        $user = $this->userWithPermissions(['settings.view']);

        $this->actingAs($user)
            ->get('/admin/settings/users')
            ->assertForbidden();

        $viewer = $this->userWithPermissions(['users.view']);

        $this->actingAs($viewer)
            ->get('/admin/settings/users')
            ->assertOk();
    }

    public function test_roles_page_requires_roles_view_permission(): void
    {
        $user = $this->userWithPermissions(['settings.view']);

        $this->actingAs($user)
            ->get('/admin/settings/roles-permissions')
            ->assertForbidden();

        $viewer = $this->userWithPermissions(['roles.view']);

        $this->actingAs($viewer)
            ->get('/admin/settings/roles-permissions')
            ->assertOk();
    }

    public function test_settings_overview_requires_settings_view_permission(): void
    {
        $user = $this->userWithPermissions(['contacts.view']);

        $this->actingAs($user)
            ->get('/admin/settings/overview')
            ->assertForbidden();

        $viewer = $this->userWithPermissions(['settings.view']);

        $this->actingAs($viewer)
            ->get('/admin/settings/overview')
            ->assertOk();
    }

    public function test_contacts_module_requires_contacts_view_permission(): void
    {
        $user = $this->userWithPermissions(['settings.view']);

        $this->actingAs($user)
            ->get('/admin/contacts/customers')
            ->assertForbidden();

        Livewire::actingAs($this->userWithPermissions(['contacts.view']))
            ->test(Customers::class)
            ->assertOk();
    }

    public function test_purchasing_module_requires_purchasing_view_permission(): void
    {
        $user = $this->userWithPermissions(['contacts.view']);

        $this->actingAs($user)
            ->get('/admin/purchasing-inventory/purchases')
            ->assertForbidden();

        $this->actingAs($this->userWithPermissions(['purchasing-inventory.view']))
            ->get('/admin/purchasing-inventory/purchases')
            ->assertOk();
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    protected function userWithPermissions(array $permissionKeys): User
    {
        $role = Role::query()->create([
            'name' => 'Test Role ' . md5(implode(',', $permissionKeys)),
            'slug' => 'test_' . substr(md5(implode(',', $permissionKeys)), 0, 12),
            'description' => 'Permission test role',
            'is_system' => false,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);

        return User::factory()->create([
            'user_number' => 'USR-' . substr(md5(implode(',', $permissionKeys)), 0, 6),
            'role_id' => $role->id,
            'status' => User::STATUS_ACTIVE,
        ]);
    }
}
