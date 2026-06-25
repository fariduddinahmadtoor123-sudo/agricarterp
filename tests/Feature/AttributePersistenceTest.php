<?php

namespace Tests\Feature;

use App\Models\Attribute;
use App\Models\User;
use App\Services\ProductCatalog\AttributeCodeGenerator;
use App\Services\ProductCatalog\AttributePersistenceService;
use Database\Seeders\StandardAttributesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttributePersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_attribute_with_generated_number(): void
    {
        $attribute = app(AttributePersistenceService::class)->create([
            'name' => 'Color',
        ]);

        $this->assertSame('ATT-000001', $attribute->attribute_number);
        $this->assertSame('Color', $attribute->name);
        $this->assertSame(Attribute::STATUS_ACTIVE, $attribute->status);
    }

    public function test_generates_incrementing_attribute_numbers(): void
    {
        $generator = app(AttributeCodeGenerator::class);

        $this->assertSame('ATT-000001', $generator->generate());
        $this->assertSame('ATT-000002', $generator->generate());
    }

    public function test_rejects_duplicate_attribute_name(): void
    {
        app(AttributePersistenceService::class)->create(['name' => 'Color']);

        $this->expectException(ValidationException::class);

        app(AttributePersistenceService::class)->create(['name' => 'color']);
    }

    public function test_rejects_duplicate_attribute_name_with_spaces(): void
    {
        app(AttributePersistenceService::class)->create(['name' => 'Thread Size']);

        $this->expectException(ValidationException::class);

        app(AttributePersistenceService::class)->create(['name' => '  thread size  ']);
    }

    public function test_allows_edit_without_changing_name(): void
    {
        $attribute = app(AttributePersistenceService::class)->create(['name' => 'Weight']);

        $updated = app(AttributePersistenceService::class)->update($attribute, [
            'name' => 'weight',
        ]);

        $this->assertSame('weight', $updated->name);
    }

    public function test_attribute_number_never_changes_on_update(): void
    {
        $attribute = app(AttributePersistenceService::class)->create(['name' => 'Material']);

        $originalNumber = $attribute->attribute_number;

        app(AttributePersistenceService::class)->update($attribute, [
            'name' => 'Material Grade',
        ]);

        $this->assertSame($originalNumber, $attribute->fresh()->attribute_number);
        $this->assertSame('Material Grade', $attribute->fresh()->name);
    }

    public function test_trims_name_on_create(): void
    {
        $attribute = app(AttributePersistenceService::class)->create([
            'name' => '  Length  ',
        ]);

        $this->assertSame('Length', $attribute->name);
    }

    public function test_super_admin_can_archive_and_restore(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $attribute = app(AttributePersistenceService::class)->create(['name' => 'Voltage']);

        app(AttributePersistenceService::class)->archive($attribute);
        $this->assertSame(Attribute::STATUS_ARCHIVED, $attribute->fresh()->status);

        app(AttributePersistenceService::class)->restore($attribute);
        $this->assertSame(Attribute::STATUS_ACTIVE, $attribute->fresh()->status);
    }

    public function test_staff_cannot_archive(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $attribute = app(AttributePersistenceService::class)->create(['name' => 'RPM']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AttributePersistenceService::class)->archive($attribute);
    }

    public function test_standard_attributes_seeder_is_idempotent(): void
    {
        $this->seed(StandardAttributesSeeder::class);
        $firstCount = Attribute::query()->count();

        $this->seed(StandardAttributesSeeder::class);
        $secondCount = Attribute::query()->count();

        $this->assertSame(78, $firstCount);
        $this->assertSame(78, $secondCount);
    }

    public function test_standard_attributes_catalog_has_seventy_eight_unique_names(): void
    {
        $names = StandardAttributesSeeder::standardAttributeNames();

        $this->assertCount(78, $names);
        $this->assertCount(78, array_unique(array_map(
            fn (string $name): string => Attribute::normalizeName($name),
            $names,
        )));

        $this->assertNotContains('Model', $names);
        $this->assertNotContains('Series', $names);
    }

    public function test_standard_attributes_seeder_includes_core_specification_names(): void
    {
        $this->seed(StandardAttributesSeeder::class);

        foreach (['Color', 'Weight', 'Length', 'Material', 'Voltage', 'Pressure', 'RPM', 'Thread Size'] as $name) {
            $this->assertNotNull(
                Attribute::query()->whereNormalizedName($name)->first(),
                "Missing standard attribute: {$name}",
            );
        }
    }
}
