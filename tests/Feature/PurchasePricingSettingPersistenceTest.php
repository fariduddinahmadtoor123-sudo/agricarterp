<?php

namespace Tests\Feature;

use App\Services\Settings\PurchasePricingSettingPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PurchasePricingSettingPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_purchase_pricing_setting_record(): void
    {
        $setting = app(PurchasePricingSettingPersistenceService::class)->create($this->payload());

        $this->assertFalse($setting->update_product_prices_from_purchases);
        $this->assertSame('10.00', (string) $setting->wholesale_markup_pct);
        $this->assertSame('8.00', (string) $setting->super_wholesale_markup_pct);
        $this->assertSame('12.00', (string) $setting->distributor_markup_pct);
        $this->assertSame('S', $setting->price_code_wording['0']);
        $this->assertSame('B', $setting->price_code_wording['9']);
    }

    public function test_rejects_second_purchase_pricing_setting_record(): void
    {
        app(PurchasePricingSettingPersistenceService::class)->create($this->payload());

        $this->expectException(ValidationException::class);

        app(PurchasePricingSettingPersistenceService::class)->create($this->payload([
            'wholesale_markup_pct' => '15',
        ]));
    }

    public function test_updates_existing_purchase_pricing_setting(): void
    {
        $setting = app(PurchasePricingSettingPersistenceService::class)->create($this->payload());

        $updated = app(PurchasePricingSettingPersistenceService::class)->update($setting, $this->payload([
            'update_product_prices_from_purchases' => true,
            'wholesale_markup_pct' => '11.5',
            'price_code_wording' => array_replace($this->payload()['price_code_wording'], [
                '0' => 'Zero',
            ]),
        ]));

        $this->assertTrue($updated->update_product_prices_from_purchases);
        $this->assertSame('11.50', (string) $updated->wholesale_markup_pct);
        $this->assertSame('Zero', $updated->price_code_wording['0']);
    }

    public function test_normalizes_markup_percentages(): void
    {
        $setting = app(PurchasePricingSettingPersistenceService::class)->create($this->payload([
            'wholesale_markup_pct' => '10,5',
            'super_wholesale_markup_pct' => '7.25',
            'distributor_markup_pct' => '1000',
        ]));

        $this->assertSame('10.50', (string) $setting->wholesale_markup_pct);
        $this->assertSame('7.25', (string) $setting->super_wholesale_markup_pct);
        $this->assertSame('999.99', (string) $setting->distributor_markup_pct);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'update_product_prices_from_purchases' => false,
            'wholesale_markup_pct' => '10',
            'super_wholesale_markup_pct' => '8',
            'distributor_markup_pct' => '12',
            'price_code_wording' => config('settings.purchase_pricing.default_price_code_wording'),
        ], $overrides);
    }
}
