<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use App\Services\ProductCatalog\UnitCodeGenerator;
use App\Services\ProductCatalog\UnitPersistenceService;
use Database\Seeders\StandardUnitsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UnitPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_unit_with_generated_number(): void
    {
        $unit = app(UnitPersistenceService::class)->create($this->unitPayload([
            'name_en' => 'Kilogram',
            'abbreviation_en' => 'kg',
            'unit_type' => Unit::TYPE_WEIGHT,
        ]));

        $this->assertSame('UNI-000001', $unit->unit_number);
        $this->assertSame('Kilogram', $unit->name_en);
        $this->assertSame('kg', $unit->abbreviation_en);
        $this->assertSame(Unit::TYPE_WEIGHT, $unit->unit_type);
        $this->assertSame(Unit::STATUS_ACTIVE, $unit->status);
        $this->assertFalse($unit->is_standard);
    }

    public function test_generates_incrementing_unit_numbers(): void
    {
        $generator = app(UnitCodeGenerator::class);

        $this->assertSame('UNI-000001', $generator->generate());
        $this->assertSame('UNI-000002', $generator->generate());
    }

    public function test_rejects_duplicate_unit_name(): void
    {
        app(UnitPersistenceService::class)->create($this->unitPayload([
            'name_en' => 'Kilogram',
            'abbreviation_en' => 'kg',
        ]));

        $this->expectException(ValidationException::class);

        app(UnitPersistenceService::class)->create($this->unitPayload([
            'name_en' => 'kilogram',
            'abbreviation_en' => 'KG2',
        ]));
    }

    public function test_rejects_duplicate_abbreviation(): void
    {
        app(UnitPersistenceService::class)->create($this->unitPayload([
            'name_en' => 'Kilogram',
            'abbreviation_en' => 'kg',
        ]));

        $this->expectException(ValidationException::class);

        app(UnitPersistenceService::class)->create($this->unitPayload([
            'name_en' => 'Kilo',
            'abbreviation_en' => ' KG ',
        ]));
    }

    public function test_requires_unit_type(): void
    {
        $this->expectException(ValidationException::class);

        app(UnitPersistenceService::class)->create([
            'name_en' => 'Kilogram',
            'abbreviation_en' => 'kg',
        ]);
    }

    public function test_allows_edit_without_changing_name_or_abbreviation(): void
    {
        $unit = app(UnitPersistenceService::class)->create($this->unitPayload([
            'name_en' => 'Liter',
            'abbreviation_en' => 'L',
            'unit_type' => Unit::TYPE_VOLUME,
        ]));

        $updated = app(UnitPersistenceService::class)->update($unit, [
            'name_en' => 'liter',
            'abbreviation_en' => 'l',
            'unit_type' => Unit::TYPE_VOLUME,
            'usage_notes' => 'Liquid volume measure.',
        ]);

        $this->assertSame('liter', $updated->name_en);
        $this->assertSame('l', $updated->abbreviation_en);
        $this->assertSame('Liquid volume measure.', $updated->usage_notes);
    }

    public function test_unit_number_never_changes_on_update(): void
    {
        $unit = app(UnitPersistenceService::class)->create($this->unitPayload([
            'name_en' => 'Piece',
            'abbreviation_en' => 'pcs',
            'unit_type' => Unit::TYPE_COUNT,
        ]));

        $originalNumber = $unit->unit_number;

        app(UnitPersistenceService::class)->update($unit, [
            'name_en' => 'Pieces',
            'abbreviation_en' => 'pcs',
            'unit_type' => Unit::TYPE_COUNT,
        ]);

        $this->assertSame($originalNumber, $unit->fresh()->unit_number);
    }

    public function test_super_admin_can_archive_and_restore(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $unit = app(UnitPersistenceService::class)->create($this->unitPayload());

        app(UnitPersistenceService::class)->archive($unit);
        $this->assertSame(Unit::STATUS_ARCHIVED, $unit->fresh()->status);

        app(UnitPersistenceService::class)->restore($unit);
        $this->assertSame(Unit::STATUS_ACTIVE, $unit->fresh()->status);
    }

    public function test_staff_cannot_archive(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        $unit = app(UnitPersistenceService::class)->create($this->unitPayload());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(UnitPersistenceService::class)->archive($unit);
    }

    public function test_trims_name_and_abbreviation_on_create(): void
    {
        $unit = app(UnitPersistenceService::class)->create($this->unitPayload([
            'name_en' => '  Meter  ',
            'abbreviation_en' => '  m  ',
            'unit_type' => Unit::TYPE_LENGTH,
        ]));

        $this->assertSame('Meter', $unit->name_en);
        $this->assertSame('m', $unit->abbreviation_en);
    }

    public function test_standard_units_seeder_is_idempotent(): void
    {
        $this->seed(StandardUnitsSeeder::class);
        $firstCount = Unit::query()->where('is_standard', true)->count();

        $this->seed(StandardUnitsSeeder::class);
        $secondCount = Unit::query()->where('is_standard', true)->count();

        $this->assertSame(50, $firstCount);
        $this->assertSame(50, $secondCount);
    }

    public function test_standard_units_catalog_defines_fifty_unique_units(): void
    {
        $units = StandardUnitsSeeder::standardUnits();

        $this->assertCount(50, $units);

        $abbreviations = array_column($units, 'abbreviation_en');
        $this->assertCount(50, array_unique(array_map(
            fn (string $abbr): string => Unit::normalizeAbbreviation($abbr),
            $abbreviations,
        )));
    }

    public function test_standard_units_seeder_promotes_existing_catalog_matches(): void
    {
        app(UnitPersistenceService::class)->create([
            'name_en' => 'Kilogram',
            'abbreviation_en' => 'kg',
            'unit_type' => Unit::TYPE_WEIGHT,
        ]);

        $this->assertFalse(Unit::query()->whereNormalizedAbbreviation('kg')->first()->is_standard);

        $this->seed(StandardUnitsSeeder::class);

        $this->assertTrue(Unit::query()->whereNormalizedAbbreviation('kg')->first()->is_standard);
        $this->assertSame(50, Unit::query()->where('is_standard', true)->count());
    }

    public function test_standard_units_seeder_includes_square_yard(): void
    {
        $this->seed(StandardUnitsSeeder::class);

        $unit = Unit::query()->whereNormalizedAbbreviation('sq yd')->first();

        $this->assertNotNull($unit);
        $this->assertSame('Square Yard', $unit->name_en);
        $this->assertSame(Unit::TYPE_AREA, $unit->unit_type);
        $this->assertTrue($unit->is_standard);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function unitPayload(array $overrides = []): array
    {
        return array_replace([
            'name_en' => 'Test Unit',
            'abbreviation_en' => 'tu',
            'unit_type' => Unit::TYPE_COUNT,
        ], $overrides);
    }
}
