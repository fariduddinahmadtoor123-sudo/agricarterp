<?php

namespace Tests\Feature;

use App\Filament\Pages\PurchasingInventory\PurchasePlanning;
use App\Filament\Pages\PurchasingInventory\PurchasePlanningWorksheet;
use App\Models\User;
use App\Support\PurchasingInventory\PurchasePlanningSheetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePlanningPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_planning_list_page_loads_for_authenticated_user(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/purchasing-inventory/purchase-planning')
            ->assertOk();
    }

    public function test_worksheet_page_loads_for_existing_sheet(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $sheet = app(PurchasePlanningSheetRepository::class)->create([
            'title' => 'Seasonal restock',
        ]);

        $this->get('/admin/purchasing-inventory/purchase-planning/' . $sheet['id'])
            ->assertOk()
            ->assertSee('Barcode, SKU, English or Urdu name')
            ->assertSee('Save Sheet');
    }

    public function test_worksheet_page_returns_404_for_missing_sheet(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/purchasing-inventory/purchase-planning/missing-sheet-id')
            ->assertNotFound();
    }

    public function test_repository_persists_sheets_in_database(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $repository = app(PurchasePlanningSheetRepository::class);

        $sheet = $repository->create(['notes' => 'Test notes']);
        $sheet['rows'][] = [
            'line_id' => 'line-1',
            'name_en' => 'Sample Product',
            'name_ur' => 'نمونہ',
            'barcode' => '10001',
            'sku' => '10001',
            'stock' => '',
            'required_qty' => '10',
            'low_stock' => '2',
            'purchase_price' => '100',
            'landing_cost' => '110',
            'sale_price' => '150',
            'thumbnail_url' => null,
        ];
        $repository->update($sheet);

        $found = $repository->find($sheet['id']);

        $this->assertNotNull($found);
        $this->assertSame('Test notes', $found['notes']);
        $this->assertCount(1, $found['rows']);
        $this->assertStringStartsWith('PP-', $found['sheet_number']);
        $this->assertDatabaseHas('purchase_planning_sheets', ['id' => $sheet['id']]);
    }

    public function test_repository_finds_sheet_by_sheet_number_case_insensitive(): void
    {
        $repository = app(PurchasePlanningSheetRepository::class);
        $sheet = $repository->create();

        $found = $repository->findBySheetNumber(strtolower((string) $sheet['sheet_number']));

        $this->assertNotNull($found);
        $this->assertSame($sheet['id'], $found['id']);
    }

    public function test_save_sheet_redirects_to_planning_list(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $sheet = app(PurchasePlanningSheetRepository::class)->create();
        $sheet['rows'][] = [
            'line_id' => 'line-1',
            'name_en' => 'Sample Product',
            'name_ur' => 'نمونہ',
            'barcode' => '10001',
            'sku' => '10001',
            'stock' => '',
            'required_qty' => '10',
            'low_stock' => '2',
            'purchase_price' => '100',
            'landing_cost' => '110',
            'sale_price' => '150',
            'thumbnail_url' => null,
        ];
        app(PurchasePlanningSheetRepository::class)->update($sheet);

        \Livewire\Livewire::test(PurchasePlanningWorksheet::class, ['sheetId' => $sheet['id']])
            ->call('saveSheet')
            ->assertRedirect(PurchasePlanning::getUrl());
    }
}
