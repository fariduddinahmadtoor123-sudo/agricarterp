<?php

namespace Tests\Feature;

use App\Models\ContactMobileNumber;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Contacts\SupplierCodeGenerator;
use App\Services\Contacts\SupplierPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SupplierPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_supplier_with_generated_code_and_mobile_registry(): void
    {
        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'mobile_number' => '03001234567',
        ]));

        $this->assertSame('SUP-000001', $supplier->supplier_code);
        $this->assertDatabaseHas('contact_mobile_numbers', [
            'mobile_normalized' => '923001234567',
            'contactable_type' => ContactMobileNumber::CONTACTABLE_SUPPLIER,
            'contactable_id' => $supplier->id,
            'category' => ContactMobileNumber::CATEGORY_PRIMARY,
        ]);
    }

    public function test_generates_incrementing_supplier_codes(): void
    {
        $generator = app(SupplierCodeGenerator::class);

        $this->assertSame('SUP-000001', $generator->generate());
        $this->assertSame('SUP-000002', $generator->generate());
    }

    public function test_rejects_duplicate_mobile_across_suppliers(): void
    {
        app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'mobile_number' => '03001234567',
        ]));

        $this->expectException(ValidationException::class);

        app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'business_name' => 'Another Supplier',
            'mobile_number' => '0300-1234567',
        ]));
    }

    public function test_rejects_duplicate_mobile_within_same_form(): void
    {
        $this->expectException(ValidationException::class);

        app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'mobile_number' => '03001234567',
            'additional_contacts' => [
                [
                    'contact_person' => 'Ali',
                    'mobile_number' => '03001234567',
                ],
            ],
        ]));
    }

    public function test_soft_delete_keeps_mobile_registry_reserved(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'mobile_number' => '03001234567',
        ]));

        app(SupplierPersistenceService::class)->delete($supplier);

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
        $this->assertDatabaseHas('contact_mobile_numbers', [
            'mobile_normalized' => '923001234567',
        ]);

        $this->expectException(ValidationException::class);

        app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'business_name' => 'Blocked Supplier',
            'mobile_number' => '03001234567',
        ]));
    }

    public function test_update_allows_same_supplier_mobile(): void
    {
        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'mobile_number' => '03001234567',
        ]));

        $updated = app(SupplierPersistenceService::class)->update($supplier, $this->supplierPayload([
            'mobile_number' => '03001234567',
            'business_name' => 'Updated Supplier',
        ]));

        $this->assertSame('Updated Supplier', $updated->business_name);
        $this->assertDatabaseCount('contact_mobile_numbers', 1);
    }

    public function test_defaults_opening_balance_type_to_credit(): void
    {
        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload());

        $this->assertSame(Supplier::OPENING_BALANCE_CREDIT, $supplier->opening_balance_type);
    }

    public function test_defaults_status_to_active(): void
    {
        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload());

        $this->assertSame(Supplier::STATUS_ACTIVE, $supplier->status);
    }

    public function test_deleted_supplier_code_is_never_reused(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'mobile_number' => '03001234567',
        ]));

        $deletedCode = $supplier->supplier_code;

        app(SupplierPersistenceService::class)->delete($supplier);

        $replacement = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'business_name' => 'New Supplier',
            'mobile_number' => '03009876543',
        ]));

        $this->assertNotSame($deletedCode, $replacement->supplier_code);
    }

    public function test_rejects_missing_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        app(SupplierPersistenceService::class)->create([
            'business_name' => 'Incomplete Supplier',
        ]);
    }

    public function test_rejects_empty_bank_account_rows(): void
    {
        $this->expectException(ValidationException::class);

        app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'bank_accounts' => [
                [
                    'bank_name' => null,
                    'branch_name' => null,
                    'account_title' => null,
                    'iban_account_number' => null,
                ],
            ],
        ]));
    }

    public function test_rejects_invalid_primary_mobile_format(): void
    {
        $this->expectException(ValidationException::class);

        app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'mobile_number' => 'not-a-mobile',
        ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function supplierPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'supplier_type' => 'local',
            'status' => Supplier::STATUS_ACTIVE,
            'country' => 'Pakistan',
            'city' => 'Lahore',
            'full_address' => 'Test Address',
            'business_name' => 'Test Supplier',
            'contact_name' => 'John Doe',
            'mobile_number' => '03111234567',
            'credit_limit' => 1000,
            'opening_balance' => 500,
            'bank_accounts' => [
                [
                    'bank_name' => 'HBL',
                    'branch_name' => 'Main',
                    'account_title' => 'Test Supplier',
                    'iban_account_number' => 'PK00HABB0000001123456701',
                ],
            ],
            'urdu' => [],
            'additional_contacts' => [],
            'documents' => [],
        ], $overrides);
    }
}
