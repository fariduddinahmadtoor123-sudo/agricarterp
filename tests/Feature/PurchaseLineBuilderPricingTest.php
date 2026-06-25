<?php

namespace Tests\Feature;

use App\Models\PurchasePricingSetting;
use App\Services\PurchasingInventory\PurchaseLineBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseLineBuilderPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_from_product_uses_purchase_pricing_settings_markups(): void
    {
        $this->seedPurchasePricingSettings([
            'wholesale_markup_pct' => '15',
            'super_wholesale_markup_pct' => '9',
            'distributor_markup_pct' => '11',
        ]);

        $row = app(PurchaseLineBuilder::class)->fromProduct([
            'id' => 1,
            'barcode' => '10001',
            'sku' => '10001',
            'name_en' => 'Test Product',
            'name_ur' => '',
        ]);

        $this->assertSame('15', $row['wholesale_pct']);
        $this->assertSame('9', $row['super_wholesale_pct']);
        $this->assertSame('11', $row['distributor_pct']);
    }

    public function test_apply_tier_rates_calculates_wholesale_super_wholesale_and_distributor_prices(): void
    {
        $this->seedPurchasePricingSettings([
            'wholesale_markup_pct' => '10',
            'super_wholesale_markup_pct' => '8',
            'distributor_markup_pct' => '12',
        ]);

        $row = [
            'purchase_rate' => '100',
            'wholesale_pct' => '10',
            'super_wholesale_pct' => '8',
            'distributor_pct' => '12',
            'wholesale_rate' => '',
            'super_wholesale_rate' => '',
            'distributor_rate' => '',
        ];

        $updated = PurchaseLineBuilder::applyTierRates($row);

        $this->assertSame('110.00', $updated['wholesale_rate']);
        $this->assertSame('108.00', $updated['super_wholesale_rate']);
        $this->assertSame('112.00', $updated['distributor_rate']);
    }

    public function test_apply_tier_rates_can_sync_markups_from_settings(): void
    {
        $this->seedPurchasePricingSettings([
            'wholesale_markup_pct' => '20',
            'super_wholesale_markup_pct' => '6',
            'distributor_markup_pct' => '14',
        ]);

        $row = [
            'purchase_rate' => '100',
            'wholesale_pct' => '10',
            'super_wholesale_pct' => '8',
            'distributor_pct' => '12',
            'wholesale_rate' => '',
            'super_wholesale_rate' => '',
            'distributor_rate' => '',
        ];

        $updated = PurchaseLineBuilder::applyTierRates($row, syncMarkupsFromSettings: true);

        $this->assertSame('20', $updated['wholesale_pct']);
        $this->assertSame('6', $updated['super_wholesale_pct']);
        $this->assertSame('14', $updated['distributor_pct']);
        $this->assertSame('120.00', $updated['wholesale_rate']);
        $this->assertSame('106.00', $updated['super_wholesale_rate']);
        $this->assertSame('114.00', $updated['distributor_rate']);
    }

    public function test_falls_back_to_config_defaults_when_no_settings_record_exists(): void
    {
        $row = app(PurchaseLineBuilder::class)->fromProduct([
            'id' => 2,
            'barcode' => '10002',
            'sku' => '10002',
            'name_en' => 'Fallback Product',
            'name_ur' => '',
        ]);

        $this->assertSame('10', $row['wholesale_pct']);
        $this->assertSame('8', $row['super_wholesale_pct']);
        $this->assertSame('12', $row['distributor_pct']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function seedPurchasePricingSettings(array $overrides = []): PurchasePricingSetting
    {
        return PurchasePricingSetting::query()->create(array_merge([
            'update_product_prices_from_purchases' => false,
            'wholesale_markup_pct' => '10',
            'super_wholesale_markup_pct' => '8',
            'distributor_markup_pct' => '12',
            'price_code_wording' => config('settings.purchase_pricing.default_price_code_wording'),
        ], $overrides));
    }
}
