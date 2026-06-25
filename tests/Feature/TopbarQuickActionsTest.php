<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\QuickPurchaseController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopbarQuickActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_purchase_redirects_to_worksheet(): void
    {
        $user = User::factory()->superAdmin()->create(['user_number' => 'USR-000001']);

        $this->actingAs($user)
            ->get(action(QuickPurchaseController::class))
            ->assertRedirect();
    }
}
