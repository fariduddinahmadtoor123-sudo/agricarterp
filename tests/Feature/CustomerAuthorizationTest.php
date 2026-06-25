<?php

namespace Tests\Feature;

use App\Support\Contacts\CustomerAuthorization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_delete_and_restore(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $this->assertTrue(CustomerAuthorization::canDelete());
        $this->assertTrue(CustomerAuthorization::canRestore());
    }

    public function test_staff_cannot_delete_or_restore(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertFalse(CustomerAuthorization::canDelete());
        $this->assertFalse(CustomerAuthorization::canRestore());
        $this->assertTrue(CustomerAuthorization::canCreate());
        $this->assertTrue(CustomerAuthorization::canEdit());
    }
}
