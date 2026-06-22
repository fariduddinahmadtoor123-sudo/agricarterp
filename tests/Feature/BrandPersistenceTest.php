<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use App\Services\ProductCatalog\BrandPersistenceService;
use App\Services\ProductCatalog\CategoryPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BrandPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_brand_with_generated_number(): void
    {
        $brand = app(BrandPersistenceService::class)->create($this->brandPayload([
            'name_en' => 'Honda',
            'short_note' => 'Japanese manufacturer of engines and machinery.',
        ]));

        $this->assertSame('BRN-000001', $brand->brand_number);
        $this->assertSame('Honda', $brand->name_en);
        $this->assertSame(Brand::STATUS_ACTIVE, $brand->status);
        $this->assertSame(0, $brand->categories_count);
    }

    public function test_assigns_categories_to_brand(): void
    {
        $engines = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Engines',
            'name_ur' => 'انجن',
        ]);

        $pumps = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Water Pumps',
            'name_ur' => 'پمپ',
        ]);

        $brand = app(BrandPersistenceService::class)->create($this->brandPayload([
            'name_en' => 'Honda',
            'short_note' => 'Engines and pumps.',
            'category_ids' => [$engines->id, $pumps->id],
        ]));

        $this->assertSame(2, $brand->categories_count);
        $this->assertCount(2, $brand->categories);
    }

    public function test_rejects_duplicate_brand_name(): void
    {
        app(BrandPersistenceService::class)->create($this->brandPayload([
            'name_en' => 'Honda',
            'short_note' => 'First brand.',
        ]));

        $this->expectException(ValidationException::class);

        app(BrandPersistenceService::class)->create($this->brandPayload([
            'name_en' => 'honda',
            'short_note' => 'Duplicate brand.',
        ]));
    }

    public function test_rejects_duplicate_brand_name_with_spaces(): void
    {
        app(BrandPersistenceService::class)->create($this->brandPayload([
            'name_en' => 'SKF',
            'short_note' => 'Bearings brand.',
        ]));

        $this->expectException(ValidationException::class);

        app(BrandPersistenceService::class)->create($this->brandPayload([
            'name_en' => '  skf  ',
            'short_note' => 'Duplicate.',
        ]));
    }

    public function test_allows_edit_without_changing_name(): void
    {
        $brand = app(BrandPersistenceService::class)->create($this->brandPayload([
            'name_en' => 'Yanmar',
            'short_note' => 'Diesel engines.',
        ]));

        $updated = app(BrandPersistenceService::class)->update($brand, [
            'name_en' => 'yanmar',
            'short_note' => 'Updated note.',
            'category_ids' => [],
        ]);

        $this->assertSame('yanmar', $updated->name_en);
        $this->assertSame('Updated note.', $updated->short_note);
    }

    public function test_rejects_archived_category_assignment(): void
    {
        $category = app(CategoryPersistenceService::class)->create([
            'name_en' => 'Bearings',
            'name_ur' => 'بیئرنگ',
        ]);

        $this->actingAs(User::factory()->superAdmin()->create());
        app(CategoryPersistenceService::class)->archive($category);

        $this->expectException(ValidationException::class);

        app(BrandPersistenceService::class)->create($this->brandPayload([
            'name_en' => 'SKF',
            'short_note' => 'Bearings.',
            'category_ids' => [$category->id],
        ]));
    }

    public function test_brand_number_never_changes_on_update(): void
    {
        $brand = app(BrandPersistenceService::class)->create($this->brandPayload([
            'name_en' => 'Honda',
            'short_note' => 'Engines.',
        ]));

        $originalNumber = $brand->brand_number;

        app(BrandPersistenceService::class)->update($brand, [
            'name_en' => 'Honda Motor',
            'short_note' => 'Updated.',
            'category_ids' => [],
        ]);

        $this->assertSame($originalNumber, $brand->fresh()->brand_number);
    }

    public function test_super_admin_can_archive_and_restore(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $brand = app(BrandPersistenceService::class)->create($this->brandPayload());

        app(BrandPersistenceService::class)->archive($brand);
        $this->assertSame(Brand::STATUS_ARCHIVED, $brand->fresh()->status);

        app(BrandPersistenceService::class)->restore($brand);
        $this->assertSame(Brand::STATUS_ACTIVE, $brand->fresh()->status);
    }

    public function test_staff_cannot_archive(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        $brand = app(BrandPersistenceService::class)->create($this->brandPayload());

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(BrandPersistenceService::class)->archive($brand);
    }

    public function test_persists_brand_logo_path(): void
    {
        Storage::fake('local');
        config(['product-catalog.brand_logo_disk' => 'local']);

        Storage::disk('local')->put('brands/honda.png', 'logo-bytes');

        $brand = app(BrandPersistenceService::class)->create($this->brandPayload([
            'name_en' => 'Honda',
            'short_note' => 'Logo test.',
            'logo' => 'brands/honda.png',
        ]));

        $this->assertSame('brands/honda.png', $brand->logo_path);
    }

    public function test_trims_english_name_on_create(): void
    {
        $brand = app(BrandPersistenceService::class)->create($this->brandPayload([
            'name_en' => '  Honda  ',
            'short_note' => 'Trim test.',
        ]));

        $this->assertSame('Honda', $brand->name_en);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function brandPayload(array $overrides = []): array
    {
        return array_replace([
            'name_en' => 'Test Brand',
            'short_note' => 'Test short note for AI enrichment.',
            'category_ids' => [],
        ], $overrides);
    }
}
