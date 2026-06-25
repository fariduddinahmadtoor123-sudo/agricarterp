<?php

namespace Database\Seeders;

use App\Models\PurchasePricingSetting;
use Illuminate\Database\Seeder;

class PurchasePricingSettingSeeder extends Seeder
{
    public function run(): void
    {
        if (PurchasePricingSetting::query()->exists()) {
            return;
        }

        $defaults = config('settings.purchase_pricing', []);

        PurchasePricingSetting::query()->create([
            'update_product_prices_from_purchases' => (bool) ($defaults['update_product_prices_from_purchases'] ?? false),
            'wholesale_markup_pct' => $defaults['wholesale_markup_pct'] ?? '10',
            'super_wholesale_markup_pct' => $defaults['super_wholesale_markup_pct'] ?? '8',
            'distributor_markup_pct' => $defaults['distributor_markup_pct'] ?? '12',
            'price_code_wording' => $defaults['default_price_code_wording'] ?? [],
        ]);
    }
}
