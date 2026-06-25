<?php

namespace Tests\Feature;

use App\Filament\Pages\PurchasingInventory\Purchases;
use App\Filament\Pages\PurchasingInventory\PurchaseWorksheet;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Contacts\SupplierPersistenceService;
use App\Services\PurchasingInventory\PurchaseLineBuilder;
use App\Support\PurchasingInventory\PurchaseSheetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_list_page_loads(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/purchasing-inventory/purchases')
            ->assertOk();
    }

    public function test_worksheet_loads_for_existing_purchase(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $sheet = app(PurchaseSheetRepository::class)->create();

        $this->get('/admin/purchasing-inventory/purchases/' . $sheet['id'])
            ->assertOk()
            ->assertSee('Save Invoice')
            ->assertSee('Select supplier');
    }

    public function test_repository_persists_purchase_in_database(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $repository = app(PurchaseSheetRepository::class);
        $sheet = $repository->create();

        $this->assertMatchesRegularExpression('/^PU-\d{8}-\d{4}$/', $sheet['purchase_number']);
        $this->assertSame('unpaid', $sheet['invoice_payment_status']);
        $this->assertDatabaseHas('purchase_sheets', ['id' => $sheet['id']]);
    }

    public function test_line_builder_calculates_invoice_total(): void
    {
        $rows = [
            ['purchase_qty' => '2', 'purchase_rate' => '100'],
            ['purchase_qty' => '1', 'purchase_rate' => '50'],
        ];

        $this->assertSame(250.0, PurchaseLineBuilder::invoiceTotal($rows));
    }

    public function test_format_quantity_strips_trailing_decimals(): void
    {
        $this->assertSame('50', PurchaseLineBuilder::formatQuantity('50.0000'));
        $this->assertSame('9999', PurchaseLineBuilder::formatQuantity(9999));
        $this->assertSame('', PurchaseLineBuilder::formatQuantity(''));
    }

    public function test_from_product_prefills_catalog_quantities(): void
    {
        $row = app(PurchaseLineBuilder::class)->fromProduct([
            'id' => 1,
            'barcode' => '10001',
            'sku' => '10001',
            'name_en' => 'Test',
            'name_ur' => '',
            'required_quantity' => 100,
            'alert_quantity' => 50.0,
        ]);

        $this->assertSame('100', $row['required_qty']);
        $this->assertSame('50', $row['alert_qty']);
    }

    public function test_save_purchase_redirects_when_valid(): void
    {
        $user = User::factory()->superAdmin()->create();
        $supplier = app(SupplierPersistenceService::class)->create([
            'supplier_type' => 'local',
            'status' => Supplier::STATUS_ACTIVE,
            'country' => 'Pakistan',
            'city' => 'Lahore',
            'full_address' => 'Test Address',
            'business_name' => 'Purchase Test Supplier',
            'contact_name' => 'John Doe',
            'mobile_number' => '03111234568',
            'credit_limit' => 1000,
            'opening_balance' => 500,
            'bank_accounts' => [
                [
                    'bank_name' => 'HBL',
                    'branch_name' => 'Main',
                    'account_title' => 'Purchase Test Supplier',
                    'iban_account_number' => 'PK00HABB0000001123456703',
                ],
            ],
            'urdu' => [],
            'additional_contacts' => [],
            'documents' => [],
        ]);

        $this->actingAs($user);

        $sheet = app(PurchaseSheetRepository::class)->create([
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->business_name,
        ]);
        $sheet['rows'][] = app(PurchaseLineBuilder::class)->fromProduct([
            'id' => 1,
            'barcode' => '10001',
            'sku' => '10001',
            'name_en' => 'Sample',
            'name_ur' => '',
            'thumbnail_url' => null,
            'low_stock' => '5',
        ]);
        $sheet['rows'][0]['purchase_qty'] = '2';
        $sheet['rows'][0]['purchase_rate'] = '100';
        app(PurchaseSheetRepository::class)->update($sheet);

        \Livewire\Livewire::test(PurchaseWorksheet::class, ['purchaseId' => $sheet['id']])
            ->set('supplierId', $supplier->id)
            ->call('savePurchase')
            ->assertRedirect(Purchases::getUrl());
    }
}
