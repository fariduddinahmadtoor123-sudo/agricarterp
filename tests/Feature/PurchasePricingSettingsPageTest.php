<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings\PurchasePricing;
use App\Models\PurchasePricingSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchasePricingSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_loads_for_authenticated_user(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get('/admin/settings/purchase-pricing')
            ->assertOk()
            ->assertSee('No purchase pricing settings yet');
    }

    public function test_create_action_hidden_when_record_exists(): void
    {
        $user = User::factory()->superAdmin()->create();

        PurchasePricingSetting::query()->create([
            'wholesale_markup_pct' => '10',
            'super_wholesale_markup_pct' => '8',
            'distributor_markup_pct' => '12',
            'price_code_wording' => config('settings.purchase_pricing.default_price_code_wording'),
        ]);

        Livewire::actingAs($user)
            ->test(PurchasePricing::class)
            ->assertDontSee('Add Purchase Pricing');
    }

    public function test_add_button_visible_when_no_record_exists(): void
    {
        $user = User::factory()->superAdmin()->create();

        Livewire::actingAs($user)
            ->test(PurchasePricing::class)
            ->assertSee('Add Purchase Pricing');
    }

    public function test_table_shows_existing_record(): void
    {
        $user = User::factory()->superAdmin()->create();

        PurchasePricingSetting::query()->create([
            'wholesale_markup_pct' => '15',
            'super_wholesale_markup_pct' => '9',
            'distributor_markup_pct' => '11',
            'price_code_wording' => config('settings.purchase_pricing.default_price_code_wording'),
        ]);

        Livewire::actingAs($user)
            ->test(PurchasePricing::class)
            ->assertSee('15%');
    }
}
