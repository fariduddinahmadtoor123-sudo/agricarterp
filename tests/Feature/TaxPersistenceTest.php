<?php

namespace Tests\Feature;

use App\Models\Tax;
use App\Models\User;
use App\Services\Settings\TaxPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaxPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->superAdmin()->create());
    }

    public function test_creates_tax_record(): void
    {
        $tax = app(TaxPersistenceService::class)->create($this->payload());

        $this->assertSame('GST', $tax->name);
        $this->assertSame('GST', $tax->code);
        $this->assertSame(Tax::TYPE_PERCENTAGE, $tax->type);
        $this->assertSame('17.0000', (string) $tax->rate_value);
        $this->assertSame(['sale', 'purchase'], $tax->apply_on);
        $this->assertTrue($tax->isActive());
    }

    public function test_rejects_duplicate_tax_code(): void
    {
        app(TaxPersistenceService::class)->create($this->payload());

        $this->expectException(ValidationException::class);

        app(TaxPersistenceService::class)->create($this->payload([
            'name' => 'Another GST',
        ]));
    }

    public function test_rejects_percentage_above_one_hundred(): void
    {
        $this->expectException(ValidationException::class);

        app(TaxPersistenceService::class)->create($this->payload([
            'rate_value' => '101',
        ]));
    }

    public function test_updates_existing_tax(): void
    {
        $tax = app(TaxPersistenceService::class)->create($this->payload());

        $updated = app(TaxPersistenceService::class)->update($tax, $this->payload([
            'name' => 'Sales Tax',
            'code' => 'ST',
            'rate_value' => '18',
            'apply_on' => ['sale'],
            'status' => Tax::STATUS_INACTIVE,
        ]));

        $this->assertSame('Sales Tax', $updated->name);
        $this->assertSame('ST', $updated->code);
        $this->assertSame(Tax::STATUS_INACTIVE, $updated->status);
        $this->assertSame(['sale'], $updated->apply_on);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'GST',
            'code' => 'gst',
            'type' => Tax::TYPE_PERCENTAGE,
            'rate_value' => '17',
            'apply_on' => ['sale', 'purchase'],
            'status' => Tax::STATUS_ACTIVE,
            'notes' => 'Standard GST master record',
        ], $overrides);
    }
}
