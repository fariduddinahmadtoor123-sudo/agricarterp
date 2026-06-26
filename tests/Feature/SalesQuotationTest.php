<?php

namespace Tests\Feature;

use App\Filament\Pages\SalesPos\SalesQuotationWorksheet;
use App\Models\PurchasingInventory\ProductStorePrice;
use App\Models\SalesPos\SalesQuotation;
use App\Models\User;
use App\Services\SalesPos\PosSaleLineBuilder;
use App\Support\SalesPos\SalesQuotationRepository;

class SalesQuotationTest extends PurchasingInventoryPersistenceTest
{
    public function test_sales_quotations_list_page_loads(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/sales-pos/sales-quotations')
            ->assertOk();
    }

    public function test_worksheet_loads_for_existing_quotation(): void
    {
        $sheet = app(SalesQuotationRepository::class)->create();

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/sales-pos/sales-quotations/' . $sheet['id'])
            ->assertOk();
    }

    public function test_repository_persists_sales_quotation_in_database(): void
    {
        $sheet = app(SalesQuotationRepository::class)->create();

        $this->assertStringStartsWith('SQ-', $sheet['quotation_number']);
        $this->assertDatabaseHas('sales_quotations', ['id' => $sheet['id']]);
    }

    public function test_hold_and_load_quotation(): void
    {
        $repository = app(SalesQuotationRepository::class);
        $sheet = $repository->create();
        $sheet['rows'][] = app(PosSaleLineBuilder::class)->fromProduct([
            'id' => $this->createCatalogProduct('Quote Product')->id,
            'product_number' => 'PRD-Q-001',
            'name_en' => 'Quote Product',
            'sale_rate' => '50',
            'qty' => 1,
        ]);
        $sheet['status'] = SalesQuotation::STATUS_HELD;
        $sheet['held_label'] = 'Test hold';
        $repository->update($sheet);

        $held = $repository->held();
        $this->assertNotEmpty($held);

        $found = $repository->find((string) $sheet['id']);
        $this->assertSame(SalesQuotation::STATUS_HELD, $found['status']);
    }

    public function test_finalize_quotation_does_not_reduce_stock(): void
    {
        $product = $this->createCatalogProduct('Finalize Quote Product');
        ProductStorePrice::query()->updateOrCreate(
            ['product_id' => $product->id, 'store_key' => 'main'],
            ['sale_rate' => 120],
        );

        $repository = app(SalesQuotationRepository::class);
        $sheet = $repository->create();
        $sheet['rows'][] = app(PosSaleLineBuilder::class)->fromProduct([
            'id' => $product->id,
            'product_number' => (string) $product->product_number,
            'name_en' => (string) $product->name_en,
            'sale_rate' => '120',
            'qty' => 2,
        ]);

        $before = app(\App\Services\PurchasingInventory\InventoryService::class)->onHand((int) $product->id, 'main');
        $finalized = $repository->finalize($sheet);
        $after = app(\App\Services\PurchasingInventory\InventoryService::class)->onHand((int) $product->id, 'main');

        $this->assertSame(SalesQuotation::STATUS_FINALIZED, $finalized['status']);
        $this->assertSame($before, $after);
    }

    public function test_livewire_can_add_product_from_search(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $product = $this->createCatalogProduct('Quotation Search Product');

        ProductStorePrice::query()->updateOrCreate(
            ['product_id' => $product->id, 'store_key' => 'main'],
            ['sale_rate' => 75],
        );

        $sheet = app(SalesQuotationRepository::class)->create();

        \Livewire\Livewire::test(SalesQuotationWorksheet::class, ['quotationId' => $sheet['id']])
            ->set('productSearch', (string) $product->product_number)
            ->call('addProductFromSearch')
            ->assertCount('rows', 1);
    }
}
