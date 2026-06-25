<?php

namespace Tests\Feature;

use App\Models\ContactMobileNumber;
use App\Models\Supplier;
use App\Services\Contacts\SupplierPersistenceService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContactMobileNumberRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_same_mobile_on_supplier_and_customer(): void
    {
        ContactMobileNumber::query()->create([
            'mobile_normalized' => '923001234567',
            'contactable_type' => ContactMobileNumber::CONTACTABLE_CUSTOMER,
            'contactable_id' => 1,
            'category' => ContactMobileNumber::CATEGORY_PRIMARY,
        ]);

        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'mobile_number' => '03001234567',
        ]));

        $this->assertDatabaseHas('contact_mobile_numbers', [
            'mobile_normalized' => '923001234567',
            'contactable_type' => ContactMobileNumber::CONTACTABLE_SUPPLIER,
            'contactable_id' => $supplier->id,
        ]);

        $this->assertDatabaseHas('contact_mobile_numbers', [
            'mobile_normalized' => '923001234567',
            'contactable_type' => ContactMobileNumber::CONTACTABLE_CUSTOMER,
            'contactable_id' => 1,
        ]);
    }

    public function test_rejects_duplicate_mobile_across_two_customers_at_database_level(): void
    {
        ContactMobileNumber::query()->create([
            'mobile_normalized' => '923001234567',
            'contactable_type' => ContactMobileNumber::CONTACTABLE_CUSTOMER,
            'contactable_id' => 1,
            'category' => ContactMobileNumber::CATEGORY_PRIMARY,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        ContactMobileNumber::query()->create([
            'mobile_normalized' => '923001234567',
            'contactable_type' => ContactMobileNumber::CONTACTABLE_CUSTOMER,
            'contactable_id' => 2,
            'category' => ContactMobileNumber::CATEGORY_PRIMARY,
        ]);
    }

    public function test_rejects_duplicate_mobile_across_two_suppliers(): void
    {
        app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'mobile_number' => '03001234567',
        ]));

        $this->expectException(ValidationException::class);

        app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'business_name' => 'Second Supplier',
            'mobile_number' => '03001234567',
        ]));
    }

    public function test_customer_mobile_does_not_block_supplier_update(): void
    {
        ContactMobileNumber::query()->create([
            'mobile_normalized' => '923001234567',
            'contactable_type' => ContactMobileNumber::CONTACTABLE_CUSTOMER,
            'contactable_id' => 1,
            'category' => ContactMobileNumber::CATEGORY_PRIMARY,
        ]);

        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'mobile_number' => '03009876543',
        ]));

        $updated = app(SupplierPersistenceService::class)->update($supplier, $this->supplierPayload([
            'mobile_number' => '03001234567',
            'business_name' => 'Updated Supplier',
        ]));

        $this->assertSame('Updated Supplier', $updated->business_name);
        $this->assertDatabaseHas('contact_mobile_numbers', [
            'mobile_normalized' => '923001234567',
            'contactable_type' => ContactMobileNumber::CONTACTABLE_SUPPLIER,
            'contactable_id' => $supplier->id,
        ]);
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
