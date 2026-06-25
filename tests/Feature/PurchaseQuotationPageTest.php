<?php

namespace Tests\Feature;

use App\Filament\Pages\PurchasingInventory\PurchaseQuotations;
use App\Filament\Pages\PurchasingInventory\PurchaseQuotationWorksheet;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Contacts\SupplierPersistenceService;
use App\Support\PurchasingInventory\PurchaseQuotationSheetRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseQuotationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_list_page_loads_for_authenticated_user(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/purchasing-inventory/purchase-quotations')
            ->assertOk();
    }

    public function test_worksheet_page_loads_for_existing_quotation(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $sheet = app(PurchaseQuotationSheetRepository::class)->create([
            'title' => 'Seasonal quote',
        ]);

        $this->get('/admin/purchasing-inventory/purchase-quotations/' . $sheet['id'])
            ->assertOk()
            ->assertSee('Barcode, SKU, English or Urdu name')
            ->assertSee('Save Quotation')
            ->assertSee('Print')
            ->assertSee('Select supplier');
    }

    public function test_worksheet_page_returns_404_for_missing_quotation(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/admin/purchasing-inventory/purchase-quotations/missing-id')
            ->assertNotFound();
    }

    public function test_repository_persists_quotations_in_database(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $repository = app(PurchaseQuotationSheetRepository::class);

        $sheet = $repository->create(['notes' => 'Quote notes']);
        $sheet['rows'][] = [
            'line_id' => 'line-1',
            'name_en' => 'Sample Product',
            'name_ur' => 'نمونہ',
            'barcode' => '10001',
            'sku' => '10001',
            'required_qty' => '5',
            'unit_price' => '100',
            'thumbnail_url' => null,
        ];
        $repository->update($sheet);

        $found = $repository->find($sheet['id']);

        $this->assertNotNull($found);
        $this->assertSame('Quote notes', $found['notes']);
        $this->assertCount(1, $found['rows']);
        $this->assertStringStartsWith('PQ-', $found['quotation_number']);
        $this->assertStringContainsString('(Demo)', (string) $found['store_name']);
        $this->assertDatabaseHas('purchase_quotation_sheets', ['id' => $sheet['id']]);
    }

    public function test_repository_finds_quotation_by_number_case_insensitive(): void
    {
        $repository = app(PurchaseQuotationSheetRepository::class);
        $sheet = $repository->create();

        $found = $repository->findByQuotationNumber(strtolower((string) $sheet['quotation_number']));

        $this->assertNotNull($found);
        $this->assertSame($sheet['id'], $found['id']);
    }

    public function test_save_quotation_redirects_to_list_when_valid(): void
    {
        $user = User::factory()->superAdmin()->create();
        $supplier = app(SupplierPersistenceService::class)->create([
            'supplier_type' => 'local',
            'status' => Supplier::STATUS_ACTIVE,
            'country' => 'Pakistan',
            'city' => 'Lahore',
            'full_address' => 'Test Address',
            'business_name' => 'Quote Test Supplier',
            'contact_name' => 'John Doe',
            'mobile_number' => '03111234567',
            'credit_limit' => 1000,
            'opening_balance' => 0,
            'bank_accounts' => [
                [
                    'bank_name' => 'HBL',
                    'branch_name' => 'Main',
                    'account_title' => 'Quote Test Supplier',
                    'iban_account_number' => 'PK00HABB0000001123456702',
                ],
            ],
            'urdu' => [],
            'additional_contacts' => [],
            'documents' => [],
        ]);

        $this->actingAs($user);

        $sheet = app(PurchaseQuotationSheetRepository::class)->create([
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->business_name,
        ]);
        $sheet['rows'][] = [
            'line_id' => 'line-1',
            'name_en' => 'Sample Product',
            'required_qty' => '2',
            'unit_price' => '50',
            'thumbnail_url' => null,
        ];
        app(PurchaseQuotationSheetRepository::class)->update($sheet);

        \Livewire\Livewire::test(PurchaseQuotationWorksheet::class, ['quotationId' => $sheet['id']])
            ->set('supplierId', $supplier->id)
            ->call('saveQuotation')
            ->assertRedirect(PurchaseQuotations::getUrl());
    }
}
