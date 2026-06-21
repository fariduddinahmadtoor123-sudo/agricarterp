<?php

namespace Tests\Unit;

use App\Filament\Contacts\Schemas\CustomerForm;
use Filament\Schemas\Schema;
use Tests\TestCase;

class CustomerFormTest extends TestCase
{
    public function test_schema_configures_without_error(): void
    {
        $schema = CustomerForm::configure(Schema::make());

        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function test_default_state_has_no_required_bank_accounts(): void
    {
        $state = CustomerForm::defaultState();

        $this->assertSame([], $state['bank_accounts']);
        $this->assertArrayHasKey('customer_name', $state);
        $this->assertNull($state['documents']['profile_photo']);
    }
}
