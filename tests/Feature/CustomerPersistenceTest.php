<?php

namespace Tests\Feature;

use App\Models\ContactMobileNumber;
use App\Models\Customer;
use App\Models\User;
use App\Services\Contacts\CustomerCodeGenerator;
use App\Services\Contacts\CustomerPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_customer_with_minimal_fields(): void
    {
        $customer = app(CustomerPersistenceService::class)->create($this->customerPayload());

        $this->assertSame('CUS-000001', $customer->customer_code);
        $this->assertSame('Test Customer', $customer->customer_name);
        $this->assertCount(0, $customer->bankAccounts);
    }

    public function test_creates_customer_with_generated_code_and_mobile_registry(): void
    {
        $customer = app(CustomerPersistenceService::class)->create($this->customerPayload([
            'mobile_number' => '03001234567',
        ]));

        $this->assertDatabaseHas('contact_mobile_numbers', [
            'mobile_normalized' => '923001234567',
            'contactable_type' => ContactMobileNumber::CONTACTABLE_CUSTOMER,
            'contactable_id' => $customer->id,
            'category' => ContactMobileNumber::CATEGORY_PRIMARY,
        ]);
    }

    public function test_generates_incrementing_customer_codes(): void
    {
        $generator = app(CustomerCodeGenerator::class);

        $this->assertSame('CUS-000001', $generator->generate());
        $this->assertSame('CUS-000002', $generator->generate());
    }

    public function test_rejects_duplicate_mobile_across_customers(): void
    {
        app(CustomerPersistenceService::class)->create($this->customerPayload([
            'mobile_number' => '03001234567',
        ]));

        $this->expectException(ValidationException::class);

        app(CustomerPersistenceService::class)->create($this->customerPayload([
            'customer_name' => 'Another Customer',
            'mobile_number' => '0300-1234567',
        ]));
    }

    public function test_allows_same_mobile_as_existing_supplier(): void
    {
        app(\App\Services\Contacts\SupplierPersistenceService::class)->create([
            'supplier_type' => 'local',
            'status' => 'active',
            'country' => 'Pakistan',
            'city' => 'Lahore',
            'full_address' => 'Supplier Address',
            'business_name' => 'Supplier Co',
            'contact_name' => 'Ali',
            'mobile_number' => '03001234567',
            'credit_limit' => 0,
            'opening_balance' => 0,
            'bank_accounts' => [
                ['bank_name' => 'HBL', 'branch_name' => 'Main', 'account_title' => 'S', 'iban_account_number' => 'PK00'],
            ],
            'urdu' => [],
            'additional_contacts' => [],
            'documents' => [],
        ]);

        $customer = app(CustomerPersistenceService::class)->create($this->customerPayload([
            'mobile_number' => '03001234567',
        ]));

        $this->assertSame('CUS-000001', $customer->customer_code);
        $this->assertDatabaseCount('contact_mobile_numbers', 2);
    }

    public function test_optional_bank_accounts_are_not_persisted_when_empty(): void
    {
        $customer = app(CustomerPersistenceService::class)->create($this->customerPayload([
            'bank_accounts' => [
                [
                    'bank_name' => null,
                    'branch_name' => null,
                    'account_title' => null,
                    'iban_account_number' => null,
                ],
            ],
        ]));

        $this->assertCount(0, $customer->bankAccounts);
    }

    public function test_persists_bank_accounts_when_provided(): void
    {
        $customer = app(CustomerPersistenceService::class)->create($this->customerPayload([
            'bank_accounts' => [
                [
                    'bank_name' => 'HBL',
                    'branch_name' => 'Main',
                    'account_title' => 'Test Customer',
                    'iban_account_number' => 'PK00HABB0000001123456701',
                ],
            ],
        ]));

        $this->assertCount(1, $customer->bankAccounts);
        $this->assertSame('HBL', $customer->bankAccounts->first()->bank_name);
    }

    public function test_soft_delete_keeps_mobile_registry_reserved(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $customer = app(CustomerPersistenceService::class)->create($this->customerPayload([
            'mobile_number' => '03001234567',
        ]));

        app(CustomerPersistenceService::class)->delete($customer);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);

        $this->expectException(ValidationException::class);

        app(CustomerPersistenceService::class)->create($this->customerPayload([
            'customer_name' => 'Blocked Customer',
            'mobile_number' => '03001234567',
        ]));
    }

    public function test_rejects_missing_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        app(CustomerPersistenceService::class)->create([
            'customer_name' => 'Incomplete Customer',
        ]);
    }

    public function test_rejects_duplicate_mobile_within_same_form(): void
    {
        $this->expectException(ValidationException::class);

        app(CustomerPersistenceService::class)->create($this->customerPayload([
            'additional_contacts' => [
                [
                    'contact_person' => 'Assistant',
                    'mobile_number' => '03111234567',
                ],
            ],
        ]));
    }

    public function test_rejects_invalid_primary_mobile_format(): void
    {
        $this->expectException(ValidationException::class);

        app(CustomerPersistenceService::class)->create($this->customerPayload([
            'mobile_number' => 'not-a-mobile',
        ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function customerPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_name' => 'Test Customer',
            'mobile_number' => '03111234567',
            'country' => null,
            'city' => null,
            'full_address' => null,
            'credit_limit' => 0,
            'opening_balance' => 0,
            'bank_accounts' => [],
            'urdu' => [],
            'additional_contacts' => [],
            'documents' => [],
        ], $overrides);
    }
}
