<?php

namespace Tests\Feature;

use App\Filament\Pages\PurchasingInventory\PurchasePaymentSheet;
use App\Filament\Pages\PurchasingInventory\PurchasePaymentSheetWorksheet;
use App\Models\User;
use App\Services\PurchasingInventory\PurchasePaymentSheetBuilder;
use App\Support\PurchasingInventory\PurchasePaymentSheetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePaymentSheetPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_sheet_list_page_loads_for_authenticated_user(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/purchasing-inventory/purchase-payment-sheet')
            ->assertOk();
    }

    public function test_worksheet_page_loads_for_existing_sheet(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $sheet = app(PurchasePaymentSheetRepository::class)->create([
            'purchaser_name' => 'Usman',
        ]);

        $this->get('/admin/purchasing-inventory/purchase-payment-sheet/' . $sheet['id'])
            ->assertOk()
            ->assertSee('Vendor Payments')
            ->assertSee('Add Supplier')
            ->assertSee('Sheet Date')
            ->assertSee('Payment Sources')
            ->assertSee('Save Sheet')
            ->assertSee('Print');
    }

    public function test_worksheet_page_returns_404_for_missing_sheet(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/purchasing-inventory/purchase-payment-sheet/missing-id')
            ->assertNotFound();
    }

    public function test_repository_persists_payment_sheet_in_database(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $repository = app(PurchasePaymentSheetRepository::class);
        $builder = app(PurchasePaymentSheetBuilder::class);

        $sheet = $repository->create(['notes' => 'Trip notes']);
        $vendorLines = $builder->blankVendorLines();
        $vendorLines[0]['vendor_name'] = 'Vendor A';
        $vendorLines[0]['payment'] = '1500';

        $sources = $builder->blankPaymentSources();
        $sources[0]['source'] = 'Shop cash';
        $sources[0]['amount'] = '5000';

        $sheet['vendor_lines'] = $vendorLines;
        $sheet['payment_sources'] = $sources;
        $repository->update($sheet);

        $found = $repository->find($sheet['id']);

        $this->assertNotNull($found);
        $this->assertSame('Trip notes', $found['notes']);
        $this->assertSame('Vendor A', $found['vendor_lines'][0]['vendor_name']);
        $this->assertStringStartsWith('PPS-', $found['sheet_number']);
        $this->assertDatabaseHas('purchase_payment_sheets', ['id' => $sheet['id']]);
    }

    public function test_builder_calculates_totals(): void
    {
        $builder = app(PurchasePaymentSheetBuilder::class);

        $vendorLines = [
            ['vendor_name' => 'A', 'payment' => '1000'],
            ['vendor_name' => 'B', 'payment' => '250.50'],
            ['vendor_name' => '', 'payment' => ''],
        ];

        $sources = [
            ['source' => 'Cash', 'amount' => '2000'],
            ['source' => '', 'amount' => ''],
        ];

        $this->assertSame(2, $builder->filledVendorCount($vendorLines));
        $this->assertSame(1250.5, $builder->vendorPaymentsTotal($vendorLines));
        $this->assertSame(2000.0, $builder->paymentSourcesTotal($sources));
    }

    public function test_repository_finds_sheet_by_number_case_insensitive(): void
    {
        $repository = app(PurchasePaymentSheetRepository::class);
        $sheet = $repository->create();

        $found = $repository->findBySheetNumber(strtolower((string) $sheet['sheet_number']));

        $this->assertNotNull($found);
        $this->assertSame($sheet['id'], $found['id']);
    }

    public function test_save_sheet_redirects_when_valid(): void
    {
        $user = User::factory()->superAdmin()->create();
        $repository = app(PurchasePaymentSheetRepository::class);
        $builder = app(PurchasePaymentSheetBuilder::class);

        $sheet = $repository->create();
        $vendorLines = $builder->blankVendorLines();
        $vendorLines[0]['vendor_name'] = 'Test Vendor';
        $vendorLines[0]['payment'] = '500';
        $sheet['vendor_lines'] = $vendorLines;
        $repository->update($sheet);

        $this->actingAs($user);

        \Livewire\Livewire::test(PurchasePaymentSheetWorksheet::class, ['sheetId' => $sheet['id']])
            ->set('purchaserName', 'Usman')
            ->call('saveSheet')
            ->assertRedirect(PurchasePaymentSheet::getUrl());

        $saved = $repository->find($sheet['id']);
        $this->assertSame('saved', $saved['status']);
    }

    public function test_save_sheet_requires_purchaser_name(): void
    {
        $user = User::factory()->superAdmin()->create();
        $repository = app(PurchasePaymentSheetRepository::class);
        $builder = app(PurchasePaymentSheetBuilder::class);

        $sheet = $repository->create();
        $vendorLines = $builder->blankVendorLines();
        $vendorLines[0]['vendor_name'] = 'Test Vendor';
        $vendorLines[0]['payment'] = '500';
        $sheet['vendor_lines'] = $vendorLines;
        $repository->update($sheet);

        $this->actingAs($user);

        \Livewire\Livewire::test(PurchasePaymentSheetWorksheet::class, ['sheetId' => $sheet['id']])
            ->set('purchaserName', '')
            ->call('saveSheet')
            ->assertNoRedirect();

        $this->assertSame('draft', $repository->find($sheet['id'])['status']);
    }
}
