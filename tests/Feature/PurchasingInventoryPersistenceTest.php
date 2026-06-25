<?php

namespace Tests\Feature;

use App\Models\PurchasingInventory\ProductStoreCost;
use App\Models\PurchasingInventory\PurchaseSheet;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProductCatalog\BrandPersistenceService;
use App\Services\ProductCatalog\CategoryPersistenceService;
use App\Services\ProductCatalog\ProductPersistenceService;
use App\Services\ProductCatalog\UnitPersistenceService;
use App\Services\PurchasingInventory\DocumentNumberService;
use App\Services\PurchasingInventory\InventoryService;
use App\Services\PurchasingInventory\PurchaseLineBuilder;
use App\Support\PurchasingInventory\PurchasePlanningSheetRepository;
use App\Support\PurchasingInventory\PurchaseSheetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchasingInventoryPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_numbers_are_sequential_per_day(): void
    {
        $service = app(DocumentNumberService::class);

        $first = $service->next('stock_adjustment');
        $second = $service->next('stock_adjustment');

        $this->assertStringStartsWith('SA-' . now()->format('Ymd') . '-', $first);
        $this->assertMatchesRegularExpression('/^SA-\d{8}-\d{4}$/', $second);
        $this->assertNotSame($first, $second);

        preg_match('/-(\d{4})$/', $first, $firstMatch);
        preg_match('/-(\d{4})$/', $second, $secondMatch);

        $this->assertSame((int) $firstMatch[1] + 1, (int) $secondMatch[1]);
    }

    public function test_planning_repository_persists_to_database(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $repository = app(PurchasePlanningSheetRepository::class);
        $sheet = $repository->create(['notes' => 'DB notes']);
        $sheet['rows'][] = [
            'line_id' => 'line-1',
            'name_en' => 'Sample Product',
            'required_qty' => '10',
        ];
        $repository->update($sheet);

        $this->assertDatabaseHas('purchase_planning_sheets', [
            'id' => $sheet['id'],
            'notes' => 'DB notes',
        ]);
        $this->assertDatabaseHas('purchase_planning_sheet_lines', [
            'sheet_id' => $sheet['id'],
            'line_id' => 'line-1',
        ]);

        $found = $repository->find($sheet['id']);
        $this->assertNotNull($found);
        $this->assertCount(1, $found['rows']);
        $this->assertSame('DB notes', $found['notes']);
    }

    public function test_purchase_repository_persists_to_database(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $repository = app(PurchaseSheetRepository::class);
        $sheet = $repository->create();

        $this->assertInstanceOf(PurchaseSheet::class, PurchaseSheet::query()->find($sheet['id']));
        $this->assertMatchesRegularExpression('/^PU-\d{8}-\d{4}$/', $sheet['purchase_number']);
    }

    public function test_tier_rates_use_purchase_rate_markup(): void
    {
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
        $this->assertSame(110.0, PurchaseLineBuilder::tierRateFromPurchase(100, 10));
    }

    public function test_goods_receipt_updates_inventory_and_wac(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $product = $this->createCatalogProduct('GR Test Product');
        $repository = app(PurchaseSheetRepository::class);
        $inventory = app(InventoryService::class);

        $sheet = $repository->create(['store_key' => 'main']);
        $sheet['status'] = 'saved';
        $sheet['rows'][] = array_merge(
            app(PurchaseLineBuilder::class)->fromProduct([
                'id' => $product->id,
                'barcode' => '90001',
                'sku' => '90001',
                'name_en' => 'GR Test Product',
                'name_ur' => '',
            ]),
            [
                'purchase_qty' => '10',
                'purchase_rate' => '100',
                'received_qty' => '10',
                'damaged_qty' => '2',
            ],
        );
        $repository->update($sheet);

        $result = $inventory->receivePurchaseGoods($repository->find($sheet['id']), $sheet['rows']);

        $this->assertSame('received', $result['goods_receipt_status']);
        $this->assertSame(8.0, $inventory->onHand($product->id, 'main'));

        $cost = ProductStoreCost::query()
            ->where('product_id', $product->id)
            ->where('store_key', 'main')
            ->first();

        $this->assertNotNull($cost);
        $this->assertSame('100.0000', (string) $cost->average_cost);
        $this->assertSame('100.0000', (string) $cost->last_purchase_cost);
    }

    public function test_partial_goods_receipt_status(): void
    {
        $rows = [
            ['purchase_qty' => '10', 'received_qty' => '5', 'damaged_qty' => ''],
            ['purchase_qty' => '4', 'received_qty' => '', 'damaged_qty' => ''],
        ];

        $this->assertSame('partial', app(InventoryService::class)->deriveGoodsReceiptStatus($rows));
    }

    protected function createCatalogProduct(string $name): \App\Models\Product
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

        $path = 'products/gr-test.jpg';
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
