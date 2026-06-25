<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use App\Services\Contacts\SupplierPersistenceService;
use App\Support\Contacts\SupplierAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_inactivate_delete_and_restore(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $this->assertTrue(SupplierAuthorization::canInactivate());
        $this->assertTrue(SupplierAuthorization::canDelete());
        $this->assertTrue(SupplierAuthorization::canRestore());
    }

    public function test_staff_cannot_inactivate_delete_or_restore(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertFalse(SupplierAuthorization::canInactivate());
        $this->assertFalse(SupplierAuthorization::canDelete());
        $this->assertFalse(SupplierAuthorization::canRestore());
        $this->assertTrue(SupplierAuthorization::canView());
        $this->assertTrue(SupplierAuthorization::canCreate());
        $this->assertTrue(SupplierAuthorization::canEdit());
    }

    public function test_staff_cannot_set_inactive_status_on_update(): void
    {
        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload());

        $user = User::factory()->create();
        $this->actingAs($user);

        $updated = app(SupplierPersistenceService::class)->update($supplier, $this->supplierPayload([
            'status' => Supplier::STATUS_INACTIVE,
        ]));

        $this->assertSame(Supplier::STATUS_ACTIVE, $updated->status);
    }

    public function test_super_admin_can_set_inactive_status_on_update(): void
    {
        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload());

        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        $updated = app(SupplierPersistenceService::class)->update($supplier, $this->supplierPayload([
            'status' => Supplier::STATUS_INACTIVE,
        ]));

        $this->assertSame(Supplier::STATUS_INACTIVE, $updated->status);
    }

    public function test_restore_returns_soft_deleted_supplier(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $supplier = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'mobile_number' => '03001234567',
        ]));

        $code = $supplier->supplier_code;

        app(SupplierPersistenceService::class)->delete($supplier);

        $restored = app(SupplierPersistenceService::class)->restore($supplier->fresh());

        $this->assertFalse($restored->trashed());
        $this->assertSame($code, $restored->supplier_code);
    }

    public function test_operational_scope_excludes_inactive_and_deleted(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $active = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'business_name' => 'Active Supplier',
            'mobile_number' => '03001111111',
        ]));

        $inactive = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'business_name' => 'Inactive Supplier',
            'mobile_number' => '03002222222',
        ]));

        app(SupplierPersistenceService::class)->setInactive($inactive);

        $deleted = app(SupplierPersistenceService::class)->create($this->supplierPayload([
            'business_name' => 'Deleted Supplier',
            'mobile_number' => '03003333333',
        ]));

        app(SupplierPersistenceService::class)->delete($deleted);

        $operationalIds = Supplier::operational()->pluck('id')->all();

        $this->assertSame([$active->id], $operationalIds);
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
