<?php

namespace Tests\Feature;

use App\Filament\Pages\SalesPos\SalesReturnWorksheet;
use App\Models\CustomerAccountEntry;
use App\Models\PurchasingInventory\ProductStorePrice;
use App\Models\Product;
use App\Models\SalesPos\PosSale;
use App\Models\SalesPos\SalesReturn;
use App\Models\User;
use App\Services\Contacts\CustomerPersistenceService;
use App\Services\PurchasingInventory\InventoryService;
use App\Services\PurchasingInventory\PurchaseLineBuilder;
use App\Services\SalesPos\PosSaleLineBuilder;
use App\Services\SalesPos\PosSaleReturnLineBuilder;
use App\Support\PurchasingInventory\PurchaseSheetRepository;
use App\Support\SalesPos\PosSaleRepository;
use App\Support\SalesPos\SalesReturnRepository;

class SalesReturnTest extends PurchasingInventoryPersistenceTest
{
    public function test_sales_returns_list_page_loads(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/sales-pos/sales-returns')
            ->assertOk();
    }

    public function test_worksheet_loads_for_existing_return(): void
    {
        $sheet = app(SalesReturnRepository::class)->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/sales-pos/sales-returns/' . $sheet['id'])
            ->assertOk();
    }

    public function test_can_load_completed_sale_and_process_return(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $product = $this->createCatalogProduct('Return Product');
        ProductStorePrice::query()->updateOrCreate(
            ['product_id' => $product->id, 'store_key' => 'main'],
            ['sale_rate' => 100],
        );

        $inventory = app(InventoryService::class);
        $this->receiveStock($product, 20);

        $posRepository = app(PosSaleRepository::class);
        $sale = $posRepository->create(['payment_method' => 'cash']);
        $sale['rows'][] = app(PosSaleLineBuilder::class)->fromProduct([
            'id' => $product->id,
            'product_number' => (string) $product->product_number,
            'name_en' => (string) $product->name_en,
            'sale_rate' => '100',
            'qty' => 2,
        ]);
        $completed = $posRepository->complete($sale);

        $onHandAfterSale = $inventory->onHand((int) $product->id, 'main');
        $this->assertSame(18.0, $onHandAfterSale);

        $returnRepository = app(SalesReturnRepository::class);
        $return = $returnRepository->create();
        $return = $returnRepository->loadFromSaleNumber((string) $return['id'], (string) $completed['sale_number']);
        $return['rows'][0]['return_qty'] = '1';
        $return['rows'][0] = PosSaleReturnLineBuilder::recalculate($return['rows'][0]);
        $return['refund_method'] = 'cash';
        $return['refund_amount'] = '100';

        $finished = $returnRepository->complete($return);

        $this->assertSame(SalesReturn::STATUS_COMPLETED, $finished['status']);
        $this->assertSame(19.0, $inventory->onHand((int) $product->id, 'main'));
        $this->assertSame('100', $finished['return_total']);

        $original = $posRepository->find((string) $completed['id']);
        $this->assertSame(PosSale::STATUS_COMPLETED, $original['status']);
        $this->assertCount(1, $original['rows']);

        $originalModel = PosSale::query()->find($completed['id']);
        $this->assertNotNull($originalModel);
        $this->assertSame('100.00', (string) $originalModel->return_total);
        $this->assertSame('100.00', (string) $originalModel->net_total);
    }

    public function test_credit_sale_return_posts_customer_account_entry(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $product = $this->createCatalogProduct('Credit Return Product');
        ProductStorePrice::query()->updateOrCreate(
            ['product_id' => $product->id, 'store_key' => 'main'],
            ['sale_rate' => 50],
        );

        $this->receiveStock($product, 10);

        $customer = app(CustomerPersistenceService::class)->create([
            'customer_name' => 'Credit Customer',
            'mobile_number' => '03119876543',
            'country' => null,
            'city' => null,
            'full_address' => null,
            'credit_limit' => 0,
            'opening_balance' => 0,
            'bank_accounts' => [],
            'urdu' => [],
            'additional_contacts' => [],
            'documents' => [],
        ]);

        $posRepository = app(PosSaleRepository::class);
        $sale = $posRepository->create([
            'payment_method' => 'credit',
            'customer_id' => $customer->id,
            'customer_name' => $customer->customer_name,
        ]);
        $sale['rows'][] = app(PosSaleLineBuilder::class)->fromProduct([
            'id' => $product->id,
            'product_number' => (string) $product->product_number,
            'name_en' => (string) $product->name_en,
            'sale_rate' => '50',
            'qty' => 1,
        ]);
        $completed = $posRepository->complete($sale);

        $returnRepository = app(SalesReturnRepository::class);
        $return = $returnRepository->create();
        $return = $returnRepository->loadFromSaleNumber((string) $return['id'], (string) $completed['sale_number']);
        $return['rows'][0]['return_qty'] = '1';
        $return['rows'][0] = PosSaleReturnLineBuilder::recalculate($return['rows'][0]);

        $finished = $returnRepository->complete($return);

        $this->assertSame(SalesReturn::REFUND_CREDITED, $finished['refund_status']);
        $this->assertDatabaseHas('customer_account_entries', [
            'customer_id' => $customer->id,
            'entry_type' => CustomerAccountEntry::TYPE_SALE_RETURN_CREDIT,
            'reference_id' => $finished['id'],
        ]);
    }

    public function test_livewire_can_load_sale_on_return_worksheet(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $product = $this->createCatalogProduct('LW Return Product');
        ProductStorePrice::query()->updateOrCreate(
            ['product_id' => $product->id, 'store_key' => 'main'],
            ['sale_rate' => 40],
        );
        $this->receiveStock($product, 5);

        $sale = app(PosSaleRepository::class)->create();
        $sale['rows'][] = app(PosSaleLineBuilder::class)->fromProduct([
            'id' => $product->id,
            'product_number' => (string) $product->product_number,
            'name_en' => (string) $product->name_en,
            'sale_rate' => '40',
            'qty' => 1,
        ]);
        $completed = app(PosSaleRepository::class)->complete($sale);

        $return = app(SalesReturnRepository::class)->create();

        \Livewire\Livewire::test(SalesReturnWorksheet::class, ['returnId' => $return['id']])
            ->set('loadSaleNumber', (string) $completed['sale_number'])
            ->call('loadSale')
            ->assertSet('sheet.sale_number', $completed['sale_number'])
            ->assertCount('rows', 1);
    }

    protected function receiveStock(Product $product, float $qty): void
    {
        $purchaseRepository = app(PurchaseSheetRepository::class);
        $inventory = app(InventoryService::class);

        $purchase = $purchaseRepository->create(['store_key' => 'main']);
        $purchase['status'] = 'saved';
        $purchase['rows'][] = array_merge(
            app(PurchaseLineBuilder::class)->fromProduct([
                'id' => $product->id,
                'barcode' => (string) $product->product_number,
                'sku' => (string) $product->product_number,
                'name_en' => (string) $product->name_en,
                'name_ur' => '',
            ]),
            [
                'purchase_qty' => (string) $qty,
                'purchase_rate' => '100',
                'received_qty' => (string) $qty,
                'damaged_qty' => '',
            ],
        );
        $purchaseRepository->update($purchase);
        $inventory->receivePurchaseGoods($purchaseRepository->find($purchase['id']), $purchase['rows']);
    }
}
