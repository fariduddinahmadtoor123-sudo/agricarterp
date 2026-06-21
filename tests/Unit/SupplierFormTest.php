<?php

namespace Tests\Unit;

use App\Filament\Contacts\Schemas\SupplierForm;
use App\Models\Supplier;
use Filament\Schemas\Schema;
use Tests\TestCase;

class SupplierFormTest extends TestCase
{
    public function test_schema_configures_without_error(): void
    {
        $schema = SupplierForm::configure(Schema::make());

        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function test_default_state_includes_minimum_bank_account_row(): void
    {
        $state = SupplierForm::defaultState();

        $this->assertCount(1, $state['bank_accounts']);
        $this->assertArrayHasKey('branch_name', $state['bank_accounts'][0]);
        $this->assertArrayHasKey('status', $state);
        $this->assertSame(Supplier::STATUS_ACTIVE, $state['status']);
    }

    public function test_normalize_state_restores_empty_bank_accounts(): void
    {
        $state = SupplierForm::normalizeState([
            'business_name' => 'Test Supplier',
            'bank_accounts' => [],
        ]);

        $this->assertCount(1, $state['bank_accounts']);
        $this->assertSame('Test Supplier', $state['business_name']);
    }
}
