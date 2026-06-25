<?php

namespace Tests\Feature;

use App\Models\ProductControl;
use App\Models\ProductControlGroup;
use App\Models\User;
use App\Services\ProductCatalog\ProductControlGroupCodeGenerator;
use App\Services\ProductCatalog\ProductControlGroupPersistenceService;
use App\Services\ProductCatalog\ProductControlPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductControlGroupPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_group_with_generated_number(): void
    {
        $control = $this->createControl('Motor winding covered', ProductControl::TYPE_WARRANTY);

        $group = app(ProductControlGroupPersistenceService::class)->create([
            'name' => 'Motor Standard Policy',
            'control_ids' => [$control->id],
        ]);

        $this->assertSame('CTG-000001', $group->group_number);
        $this->assertSame('Motor Standard Policy', $group->name);
        $this->assertSame(ProductControlGroup::STATUS_ACTIVE, $group->status);
        $this->assertSame(1, $group->controls_count);
        $this->assertCount(1, $group->controls);
    }

    public function test_generates_incrementing_group_numbers(): void
    {
        $generator = app(ProductControlGroupCodeGenerator::class);

        $this->assertSame('CTG-000001', $generator->generate());
        $this->assertSame('CTG-000002', $generator->generate());
    }

    public function test_assigns_multiple_controls_to_group(): void
    {
        $warranty = $this->createControl('Motor winding covered', ProductControl::TYPE_WARRANTY);
        $return = $this->createControl('Unused return allowed', ProductControl::TYPE_RETURN_POLICY);
        $usage = $this->createControl('Grease bearings before use', ProductControl::TYPE_USAGE_NOTE);

        $group = app(ProductControlGroupPersistenceService::class)->create([
            'name' => 'Motor Standard Policy',
            'control_ids' => [$warranty->id, $return->id, $usage->id],
        ]);

        $this->assertSame(3, $group->controls_count);
        $this->assertCount(3, $group->controls);
    }

    public function test_rejects_duplicate_group_name(): void
    {
        $control = $this->createControl('Handle with care', ProductControl::TYPE_HANDLING_ALERT);

        app(ProductControlGroupPersistenceService::class)->create([
            'name' => 'Motor Standard Policy',
            'control_ids' => [$control->id],
        ]);

        $this->expectException(ValidationException::class);

        app(ProductControlGroupPersistenceService::class)->create([
            'name' => 'motor standard policy',
            'control_ids' => [$control->id],
        ]);
    }

    public function test_rejects_group_without_controls(): void
    {
        $this->expectException(ValidationException::class);

        app(ProductControlGroupPersistenceService::class)->create([
            'name' => 'Empty Group',
            'control_ids' => [],
        ]);
    }

    public function test_rejects_archived_control_assignment(): void
    {
        $control = $this->createControl('Burnt motor not covered', ProductControl::TYPE_WARRANTY);

        $this->actingAs(User::factory()->superAdmin()->create());
        app(ProductControlPersistenceService::class)->archive($control);

        $this->expectException(ValidationException::class);

        app(ProductControlGroupPersistenceService::class)->create([
            'name' => 'Motor Policy',
            'control_ids' => [$control->id],
        ]);
    }

    public function test_group_number_never_changes_on_update(): void
    {
        $control = $this->createControl('Keep dry', ProductControl::TYPE_HANDLING_ALERT);

        $group = app(ProductControlGroupPersistenceService::class)->create([
            'name' => 'Storage Policy',
            'control_ids' => [$control->id],
        ]);

        $originalNumber = $group->group_number;

        app(ProductControlGroupPersistenceService::class)->update($group, [
            'name' => 'Dry Storage Policy',
            'control_ids' => [$control->id],
        ]);

        $this->assertSame($originalNumber, $group->fresh()->group_number);
        $this->assertSame('Dry Storage Policy', $group->fresh()->name);
    }

    public function test_syncs_controls_on_update(): void
    {
        $first = $this->createControl('Motor winding covered', ProductControl::TYPE_WARRANTY);
        $second = $this->createControl('Unused return allowed', ProductControl::TYPE_RETURN_POLICY);

        $group = app(ProductControlGroupPersistenceService::class)->create([
            'name' => 'Motor Policy',
            'control_ids' => [$first->id],
        ]);

        $updated = app(ProductControlGroupPersistenceService::class)->update($group, [
            'name' => 'Motor Policy',
            'control_ids' => [$second->id],
        ]);

        $this->assertSame(1, $updated->controls_count);
        $this->assertTrue($updated->controls->contains('id', $second->id));
        $this->assertFalse($updated->controls->contains('id', $first->id));
    }

    public function test_super_admin_can_archive_and_restore_group(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $control = $this->createControl('Handle with care', ProductControl::TYPE_HANDLING_ALERT);

        $group = app(ProductControlGroupPersistenceService::class)->create([
            'name' => 'Handling Policy',
            'control_ids' => [$control->id],
        ]);

        app(ProductControlGroupPersistenceService::class)->archive($group);
        $this->assertSame(ProductControlGroup::STATUS_ARCHIVED, $group->fresh()->status);

        app(ProductControlGroupPersistenceService::class)->restore($group);
        $this->assertSame(ProductControlGroup::STATUS_ACTIVE, $group->fresh()->status);
    }

    public function test_staff_cannot_archive_group(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $control = $this->createControl('Warning label', ProductControl::TYPE_WARNING);

        $group = app(ProductControlGroupPersistenceService::class)->create([
            'name' => 'Safety Policy',
            'control_ids' => [$control->id],
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(ProductControlGroupPersistenceService::class)->archive($group);
    }

    protected function createControl(string $name, string $type): ProductControl
    {
        return app(ProductControlPersistenceService::class)->create([
            'name' => $name,
            'control_type' => $type,
        ]);
    }
}
