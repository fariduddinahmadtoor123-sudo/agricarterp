<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchasePricingSetting;
use App\Models\PurchasingInventory\ProductStorePrice;
use App\Models\Unit;
use App\Services\ProductCatalog\BrandPersistenceService;
use App\Services\ProductCatalog\CategoryPersistenceService;
use App\Services\ProductCatalog\ProductPersistenceService;
use App\Services\ProductCatalog\UnitPersistenceService;
use App\Services\PurchasingInventory\PurchaseLineBuilder;
use App\Services\PurchasingInventory\PurchaseProductPriceSyncService;
use App\Support\PurchasingInventory\PurchaseSheetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseProductPriceSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_product_store_prices_when_setting_enabled_and_purchase_saved(): void
    {
        $product = $this->createCatalogProduct('Sync Product');

        PurchasePricingSetting::query()->create([
            'update_product_prices_from_purchases' => true,
            'wholesale_markup_pct' => '10',
            'super_wholesale_markup_pct' => '8',
            'distributor_markup_pct' => '12',
            'price_code_wording' => config('settings.purchase_pricing.default_price_code_wording'),
        ]);

        $sheet = [
            'id' => '00000000-0000-0000-0000-000000000101',
            'status' => 'saved',
            'store_key' => 'main',
            'rows' => [
                array_merge(
                    app(PurchaseLineBuilder::class)->fromProduct([
                        'id' => $product->id,
                        'barcode' => '90011',
                        'sku' => '90011',
                        'name_en' => 'Sync Product',
                        'name_ur' => '',
                    ]),
                    [
                        'purchase_rate' => '100',
                        'landing_cost' => '105',
                        'sale_rate' => '150',
                    ],
                ),
            ],
        ];

        app(PurchaseProductPriceSyncService::class)->syncIfEnabled($sheet, $sheet['rows']);

        $price = ProductStorePrice::query()
            ->where('product_id', $product->id)
            ->where('store_key', 'main')
            ->first();

        $this->assertNotNull($price);
        $this->assertSame('100.0000', (string) $price->purchase_rate);
        $this->assertSame('105.0000', (string) $price->landing_cost);
        $this->assertSame('150.0000', (string) $price->sale_rate);
        $this->assertSame('110.0000', (string) $price->wholesale_rate);
        $this->assertSame('108.0000', (string) $price->super_wholesale_rate);
        $this->assertSame('112.0000', (string) $price->distributor_rate);
    }

    public function test_does_not_sync_when_setting_disabled(): void
    {
        $product = $this->createCatalogProduct('No Sync Product');

        PurchasePricingSetting::query()->create([
            'update_product_prices_from_purchases' => false,
            'wholesale_markup_pct' => '10',
            'super_wholesale_markup_pct' => '8',
            'distributor_markup_pct' => '12',
            'price_code_wording' => config('settings.purchase_pricing.default_price_code_wording'),
        ]);

        app(PurchaseProductPriceSyncService::class)->syncIfEnabled([
            'status' => 'saved',
            'store_key' => 'main',
            'rows' => [
                [
                    'product_id' => $product->id,
                    'purchase_rate' => '100',
                ],
            ],
        ], [
            [
                'product_id' => $product->id,
                'purchase_rate' => '100',
            ],
        ]);

        $this->assertDatabaseMissing('product_store_prices', [
            'product_id' => $product->id,
            'store_key' => 'main',
        ]);
    }

    public function test_repository_update_triggers_sync_for_saved_purchase(): void
    {
        $product = $this->createCatalogProduct('Repository Sync Product');

        PurchasePricingSetting::query()->create([
            'update_product_prices_from_purchases' => true,
            'wholesale_markup_pct' => '10',
            'super_wholesale_markup_pct' => '8',
            'distributor_markup_pct' => '12',
            'price_code_wording' => config('settings.purchase_pricing.default_price_code_wording'),
        ]);

        $repository = app(PurchaseSheetRepository::class);
        $sheet = $repository->create(['store_key' => 'main']);
        $sheet['status'] = 'saved';
        $sheet['rows'][] = array_merge(
            app(PurchaseLineBuilder::class)->fromProduct([
                'id' => $product->id,
                'barcode' => '90012',
                'sku' => '90012',
                'name_en' => 'Repository Sync Product',
                'name_ur' => '',
            ]),
            [
                'purchase_rate' => '200',
                'sale_rate' => '250',
            ],
        );

        $repository->update($sheet);

        $this->assertDatabaseHas('product_store_prices', [
            'product_id' => $product->id,
            'store_key' => 'main',
            'purchase_rate' => '200.0000',
            'sale_rate' => '250.0000',
            'wholesale_rate' => '220.0000',
        ]);
    }

    protected function createCatalogProduct(string $name): Product
    {
        Storage::fake('local');

        $parent = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Purchasing Root',
            'name_ur' => '',
        ]);

        $leaf = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Purchasing Leaf',
            'name_ur' => '',
            'parent_id' => $parent->id,
        ]);

        $brand = app(BrandPersistenceService::class)->create([
            'name_en' => 'Test Brand',
            'short_note' => 'Test',
            'category_ids' => [],
        ]);

        $piece = app(UnitPersistenceService::class)->create([
            'name_en' => 'Piece',
            'abbreviation_en' => 'pcs',
            'unit_type' => Unit::TYPE_COUNT,
        ]);

        $carton = app(UnitPersistenceService::class)->create([
            'name_en' => 'Carton',
            'abbreviation_en' => 'ctn',
            'unit_type' => Unit::TYPE_PACKAGING,
        ]);

        $path = 'products/price-sync-test.jpg';
        Storage::disk('local')->put($path, 'image');

        return app(ProductPersistenceService::class)->create([
            'category_id' => $leaf->id,
            'brand_id' => $brand->id,
            'base_unit_id' => $piece->id,
            'packing_unit_id' => $carton->id,
            'packing_value' => 1,
            'name_en' => $name,
            'name_ur' => '',
            'main_image' => $path,
            'additional_images' => [],
            'required_quantity' => 20,
            'alert_quantity' => 5,
            'attribute_rows' => [],
            'display_category_ids' => [],
            'control_group_ids' => [],
            'individual_control_ids' => [],
            'images' => [],
        ]);
    }
}
