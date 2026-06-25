<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopbarQuickActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_topbar_does_not_include_global_print_shortcut(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('title="Barcode / price tag printing"', false)
            ->assertSee('title="New purchase invoice"', false)
            ->assertSee('title="New sale (POS)"', false);
    }
}
