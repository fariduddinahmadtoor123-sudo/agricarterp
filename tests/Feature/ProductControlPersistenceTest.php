<?php

namespace Tests\Feature;

use App\Models\ProductControl;
use App\Models\User;
use App\Services\ProductCatalog\ProductControlCodeGenerator;
use App\Services\ProductCatalog\ProductControlPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductControlPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_control_with_generated_number(): void
    {
        $control = app(ProductControlPersistenceService::class)->create([
            'name' => 'Motor winding covered',
            'control_type' => ProductControl::TYPE_WARRANTY,
        ]);

        $this->assertSame('CTL-000001', $control->control_number);
        $this->assertSame('Motor winding covered', $control->name);
        $this->assertSame(ProductControl::TYPE_WARRANTY, $control->control_type);
        $this->assertSame(ProductControl::STATUS_ACTIVE, $control->status);
    }

    public function test_generates_incrementing_control_numbers(): void
    {
        $generator = app(ProductControlCodeGenerator::class);

        $this->assertSame('CTL-000001', $generator->generate());
        $this->assertSame('CTL-000002', $generator->generate());
    }

    public function test_rejects_duplicate_control_name(): void
    {
        app(ProductControlPersistenceService::class)->create([
            'name' => 'Handle with care',
            'control_type' => ProductControl::TYPE_HANDLING_ALERT,
        ]);

        $this->expectException(ValidationException::class);

        app(ProductControlPersistenceService::class)->create([
            'name' => 'handle with care',
            'control_type' => ProductControl::TYPE_WARNING,
        ]);
    }

    public function test_rejects_duplicate_control_name_with_spaces(): void
    {
        app(ProductControlPersistenceService::class)->create([
            'name' => 'Keep dry',
            'control_type' => ProductControl::TYPE_HANDLING_ALERT,
        ]);

        $this->expectException(ValidationException::class);

        app(ProductControlPersistenceService::class)->create([
            'name' => '  keep dry  ',
            'control_type' => ProductControl::TYPE_USAGE_NOTE,
        ]);
    }

    public function test_allows_edit_without_changing_name(): void
    {
        $control = app(ProductControlPersistenceService::class)->create([
            'name' => 'Unused return allowed',
            'control_type' => ProductControl::TYPE_RETURN_POLICY,
        ]);

        $updated = app(ProductControlPersistenceService::class)->update($control, [
            'name' => 'unused return allowed',
            'control_type' => ProductControl::TYPE_RETURN_POLICY,
        ]);

        $this->assertSame('unused return allowed', $updated->name);
    }

    public function test_control_number_never_changes_on_update(): void
    {
        $control = app(ProductControlPersistenceService::class)->create([
            'name' => 'Grease bearings before use',
            'control_type' => ProductControl::TYPE_USAGE_NOTE,
        ]);

        $originalNumber = $control->control_number;

        app(ProductControlPersistenceService::class)->update($control, [
            'name' => 'Grease bearings monthly',
            'control_type' => ProductControl::TYPE_USAGE_NOTE,
        ]);

        $this->assertSame($originalNumber, $control->fresh()->control_number);
        $this->assertSame('Grease bearings monthly', $control->fresh()->name);
    }

    public function test_trims_name_on_create(): void
    {
        $control = app(ProductControlPersistenceService::class)->create([
            'name' => '  Burnt motor not covered  ',
            'control_type' => ProductControl::TYPE_WARRANTY,
        ]);

        $this->assertSame('Burnt motor not covered', $control->name);
    }

    public function test_rejects_invalid_control_type(): void
    {
        $this->expectException(ValidationException::class);

        app(ProductControlPersistenceService::class)->create([
            'name' => 'Invalid type control',
            'control_type' => 'invalid_type',
        ]);
    }

    public function test_accepts_replacement_policy_type(): void
    {
        $control = app(ProductControlPersistenceService::class)->create([
            'name' => 'Defective unit replaced within 7 days',
            'control_type' => ProductControl::TYPE_REPLACEMENT_POLICY,
        ]);

        $this->assertSame(ProductControl::TYPE_REPLACEMENT_POLICY, $control->control_type);
    }

    public function test_super_admin_can_archive_and_restore(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $control = app(ProductControlPersistenceService::class)->create([
            'name' => 'Handle with care',
            'control_type' => ProductControl::TYPE_HANDLING_ALERT,
        ]);

        app(ProductControlPersistenceService::class)->archive($control);
        $this->assertSame(ProductControl::STATUS_ARCHIVED, $control->fresh()->status);

        app(ProductControlPersistenceService::class)->restore($control);
        $this->assertSame(ProductControl::STATUS_ACTIVE, $control->fresh()->status);
    }

    public function test_staff_cannot_archive(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        $control = app(ProductControlPersistenceService::class)->create([
            'name' => 'Keep dry',
            'control_type' => ProductControl::TYPE_HANDLING_ALERT,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(ProductControlPersistenceService::class)->archive($control);
    }
}
