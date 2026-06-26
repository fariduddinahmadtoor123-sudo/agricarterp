<?php

namespace Tests\Feature;

use App\Filament\Pages\SalesPos\PosSaleWorksheet;
use App\Models\PurchasingInventory\ProductStorePrice;
use App\Models\SalesPos\PosSale;
use App\Models\User;
use App\Services\PurchasingInventory\InventoryService;
use App\Services\PurchasingInventory\PurchaseLineBuilder;
use App\Services\SalesPos\PosSaleLineBuilder;
use App\Support\PurchasingInventory\PurchaseSheetRepository;
use App\Support\SalesPos\PosSaleRepository;

class PosSaleTest extends PurchasingInventoryPersistenceTest
{
    public function test_pos_sales_list_page_loads(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/sales-pos/pos-sales')
            ->assertOk();
    }

    public function test_worksheet_loads_for_existing_sale(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $sheet = app(PosSaleRepository::class)->create();

        $this->get('/admin/sales-pos/pos-sales/' . $sheet['id'])
            ->assertOk()
            ->assertSee('Complete Sale')
            ->assertSee('Search name or phone');
    }

    public function test_repository_persists_pos_sale_in_database(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $sheet = app(PosSaleRepository::class)->create();

        $this->assertMatchesRegularExpression('/^PS-\d{8}-\d{4}$/', $sheet['sale_number']);
        $this->assertDatabaseHas('pos_sales', ['id' => $sheet['id']]);
    }

    public function test_hold_and_load_sale(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $repository = app(PosSaleRepository::class);
        $sheet = $repository->create();
        $sheet['rows'][] = app(PosSaleLineBuilder::class)->fromProduct([
            'id' => 1,
            'product_number' => '10001',
            'name_en' => 'Held Product',
            'name_ur' => '',
            'brand_name' => 'Brand',
            'unit_label' => 'pcs',
            'sale_rate' => '50',
            'on_hand' => '10',
        ]);
        $sheet['status'] = PosSale::STATUS_HELD;
        $sheet['held_label'] = 'Customer waiting';
        $repository->update($sheet);

        $held = $repository->held();

        $this->assertCount(1, $held);
        $this->assertSame('Customer waiting', $held[0]['held_label']);
    }

    public function test_complete_sale_reduces_stock(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $product = $this->createCatalogProduct('POS Product');

        $purchaseRepository = app(PurchaseSheetRepository::class);
        $inventory = app(InventoryService::class);

        $purchase = $purchaseRepository->create(['store_key' => 'main']);
        $purchase['status'] = 'saved';
        $purchase['rows'][] = array_merge(
            app(PurchaseLineBuilder::class)->fromProduct([
                'id' => $product->id,
                'barcode' => (string) $product->product_number,
                'sku' => (string) $product->product_number,
                'name_en' => 'POS Product',
                'name_ur' => '',
            ]),
            [
                'purchase_qty' => '10',
                'purchase_rate' => '100',
                'received_qty' => '10',
                'damaged_qty' => '',
            ],
        );
        $purchaseRepository->update($purchase);
        $inventory->receivePurchaseGoods($purchaseRepository->find($purchase['id']), $purchase['rows']);

        ProductStorePrice::query()->updateOrCreate(
            ['product_id' => $product->id, 'store_key' => 'main'],
            ['sale_rate' => 150],
        );

        $this->assertSame(10.0, $inventory->onHand($product->id, 'main'));

        $posRepository = app(PosSaleRepository::class);
        $sale = $posRepository->create(['store_key' => 'main']);
        $sale['rows'][] = app(PosSaleLineBuilder::class)->fromProduct([
            'id' => $product->id,
            'product_number' => (string) $product->product_number,
            'name_en' => 'POS Product',
            'name_ur' => '',
            'brand_name' => 'Test Brand',
            'unit_label' => 'pcs',
            'sale_rate' => '150',
            'on_hand' => '10',
            'qty' => '3',
        ]);
        $posRepository->update($sale);

        $completed = $posRepository->complete($posRepository->find($sale['id']));

        $this->assertSame(PosSale::STATUS_COMPLETED, $completed['status']);
        $this->assertTrue($completed['stock_applied']);
        $this->assertSame(7.0, $inventory->onHand($product->id, 'main'));
    }

    public function test_quick_sale_route_redirects_to_worksheet(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/quick/sale')
            ->assertRedirect();
    }

    public function test_livewire_can_add_product_from_search(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $product = $this->createCatalogProduct('Search Product');

        ProductStorePrice::query()->updateOrCreate(
            ['product_id' => $product->id, 'store_key' => 'main'],
            ['sale_rate' => 99],
        );

        $sheet = app(PosSaleRepository::class)->create();

        \Livewire\Livewire::test(PosSaleWorksheet::class, ['saleId' => $sheet['id']])
            ->set('productSearch', (string) $product->product_number)
            ->call('addProductFromSearch')
            ->assertCount('rows', 1);
    }
}
