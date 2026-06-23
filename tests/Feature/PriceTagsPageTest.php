<?php

namespace Tests\Feature;

use App\Filament\Pages\PurchasingInventory\PriceTags;
use App\Models\User;
use App\Services\PurchasingInventory\PriceTagImportService;
use App\Services\PurchasingInventory\PriceTagLineBuilder;
use App\Services\PurchasingInventory\PriceTagPresenter;
use App\Support\PurchasingInventory\PriceTagQueueRepository;
use App\Support\PurchasingInventory\PurchaseSheetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceTagsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_tags_page_loads_for_authenticated_user(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/purchasing-inventory/price-tags')
            ->assertOk()
            ->assertSee('Price tag printing')
            ->assertSee('Load Invoice')
            ->assertSee('Print Stickers');
    }

    public function test_queue_repository_persists_lines_in_session(): void
    {
        $repository = app(PriceTagQueueRepository::class);
        $builder = app(PriceTagLineBuilder::class);

        $line = $builder->fromCatalogProduct([
            'id' => 5,
            'barcode' => 'PRD-000005',
            'sku' => 'PRD-000005',
            'name_en' => 'Test Sticker Product',
            'name_ur' => '',
            'thumbnail_url' => null,
        ]);

        $repository->persistLines([$line]);

        $this->assertCount(1, $repository->lines());
        $this->assertSame(1, $repository->stickerCount());
    }

    public function test_import_service_loads_lines_from_purchase_invoice(): void
    {
        $purchaseRepo = app(PurchaseSheetRepository::class);
        $sheet = $purchaseRepo->create(['supplier_name' => 'ABC Traders']);
        $sheet['status'] = 'saved';
        $sheet['rows'][] = [
            'line_id' => 'line-import-1',
            'product_id' => 10,
            'barcode' => 'PRD-000010',
            'sku' => 'PRD-000010',
            'name_en' => 'Pump Part',
            'name_ur' => '',
            'thumbnail_url' => null,
            'purchase_qty' => '50',
            'purchase_rate' => '120',
            'landing_cost' => '125',
            'sale_rate' => '150',
            'wholesale_rate' => '140',
            'super_wholesale_rate' => '135',
            'distributor_rate' => '130',
        ];
        $purchaseRepo->update($sheet);

        $import = app(PriceTagImportService::class)->linesFromPurchaseNumber((string) $sheet['purchase_number']);

        $this->assertNotNull($import);
        $this->assertCount(1, $import['lines']);
        $this->assertSame('50', $import['lines'][0]['purchase_qty']);
        $this->assertSame('150', $import['lines'][0]['sale_rate']);
        $this->assertSame(50, $import['lines'][0]['print_qty']);
    }

    public function test_presenter_builds_sticker_data_with_toggles(): void
    {
        $presenter = app(PriceTagPresenter::class);

        $data = $presenter->stickerData([
            'name_en' => 'Pump',
            'name_ur' => 'پمپ',
            'sku' => 'PRD-000010',
            'barcode' => 'PRD-000010',
            'sale_rate' => '1500',
            'purchase_code' => 'TTT',
            'qr_url' => null,
        ], [
            'scan_mode' => 'barcode',
            'fields' => [
                'store_name' => true,
                'name_en' => true,
                'name_ur' => false,
                'sale_price' => true,
                'purchase_code' => true,
            ],
        ]);

        $this->assertSame('1500', $data['sale_price']);
        $this->assertSame('TTT', $data['purchase_code']);
        $this->assertTrue($data['show_barcode']);
        $this->assertFalse($data['show_qr']);
        $this->assertNotNull($data['barcode_svg']);
        $this->assertStringContainsString('<svg', (string) $data['barcode_svg']);
    }

    public function test_presenter_hides_barcode_when_scan_mode_is_none(): void
    {
        $presenter = app(PriceTagPresenter::class);

        $data = $presenter->stickerData([
            'barcode' => 'PRD-000010',
            'sku' => 'PRD-000010',
        ], [
            'scan_mode' => 'none',
            'fields' => [],
        ]);

        $this->assertFalse($data['show_barcode']);
        $this->assertNull($data['barcode_svg']);
    }

    public function test_livewire_clear_queue_empties_session(): void
    {
        $user = User::factory()->superAdmin()->create();
        $repository = app(PriceTagQueueRepository::class);
        $builder = app(PriceTagLineBuilder::class);

        $repository->persistLines([
            $builder->fromCatalogProduct([
                'id' => 1,
                'barcode' => 'PRD-000001',
                'sku' => 'PRD-000001',
                'name_en' => 'Item',
                'name_ur' => '',
                'thumbnail_url' => null,
            ]),
        ]);

        \Livewire\Livewire::actingAs($user)
            ->test(PriceTags::class)
            ->call('clearQueue')
            ->assertSet('queueLines', []);

        $this->assertSame([], $repository->lines());
    }
}
